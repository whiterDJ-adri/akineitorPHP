<?php

namespace Modules\Algorithm;

use Exception;

class AlgorithmService
{
    private \MySQLConnection $db;

    public function __construct()
    {
        $this->db = new \MySQLConnection();
    }

    public function createNewGame(?int $usuarioId = null): array
    {
        // Seleccionar personaje objetivo aleatorio
        $res = $this->db->query("SELECT id FROM personajes ORDER BY RAND() LIMIT 1");
        $row = $res->fetch_assoc();
        $objetivoId = $row ? (int)$row['id'] : null;

        $usuarioIdStr = $usuarioId !== null ? (string)$usuarioId : null;
        $sql = "INSERT INTO partidas (usuario_id, personaje_objetivo_id, estado, estado_json) VALUES (?, ?, 'in_progress', NULL)";
        $this->db->query($sql, [$usuarioIdStr, (string)$objetivoId]);
        $rid = $this->db->query("SELECT LAST_INSERT_ID() AS id");
        $ridRow = $rid->fetch_assoc();
        $partidaId = (int)($ridRow['id'] ?? 0);
        return ['partida_id' => $partidaId, 'personaje_objetivo_id' => $objetivoId];
    }

    public function getPartida(int $partidaId): ?array
    {
        $res = $this->db->query("SELECT * FROM partidas WHERE id = ?", [(string)$partidaId]);
        $row = $res ? $res->fetch_assoc() : null;
        return $row ?: null;
    }

    public function updatePartidaEstadoJson(int $partidaId, array $estado): void
    {
        $json = json_encode($estado, JSON_UNESCAPED_UNICODE);
        $this->db->query("UPDATE partidas SET estado_json = ? WHERE id = ?", [$json, (string)$partidaId]);
    }

    public function completePartida(int $partidaId): void
    {
        $this->db->query("UPDATE partidas SET estado = 'completed' WHERE id = ?", [(string)$partidaId]);
    }

    public function recordAnswer(int $partidaId, int $preguntaId, string $respuesta): void
    {
        $this->db->query(
            "INSERT INTO respuestas_partida (partida_id, pregunta_id, respuesta_usuario) VALUES (?, ?, ?)",
            [(string)$partidaId, (string)$preguntaId, $respuesta]
        );
    }

    public function getAskedAnswers(int $partidaId): array
    {
        $res = $this->db->query(
            "SELECT pregunta_id, respuesta_usuario FROM respuestas_partida WHERE partida_id = ? ORDER BY fecha ASC",
            [(string)$partidaId]
        );
        $asked = [];
        while ($row = $res->fetch_assoc()) {
            $asked[(int)$row['pregunta_id']] = $row['respuesta_usuario'];
        }
        return $asked;
    }

    public function getAllPersonajes(): array
    {
        $res = $this->db->query("SELECT id, nombre, descripcion, imagen_url FROM personajes");
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $list[(int)$row['id']] = $row;
        }
        return $list;
    }

    public function getAllPreguntas(): array
    {
        $res = $this->db->query("SELECT id, texto_pregunta, tipo, opciones_json FROM preguntas");
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $qid = (int)$row['id'];
            $opts = [];
            if (!empty($row['opciones_json'])) {
                $decoded = json_decode($row['opciones_json'], true);
                if (is_array($decoded)) $opts = array_values($decoded);
            }
            $list[$qid] = [
                'id' => $qid,
                'texto_pregunta' => $row['texto_pregunta'],
                'tipo' => $row['tipo'],
                'opciones' => $opts,
            ];
        }
        return $list;
    }

    public function getMapping(): array
    {
        $res = $this->db->query("SELECT personaje_id, pregunta_id, respuesta_esperada FROM personaje_pregunta");
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $pid = (int)$row['personaje_id'];
            $qid = (int)$row['pregunta_id'];
            $map[$pid][$qid] = $row['respuesta_esperada'];
        }
        return $map;
    }

    private function compatibility(string $expected, string $given): float
    {
        $expected = strtolower($expected);
        $given = strtolower($given);
        if ($expected === $given) return 1.0;
        if ($expected === 'no lo sé' || $given === 'no lo sé') return 0.6;
        // aproximaciones binarias
        $nearYes = ['sí' => 1.0, 'probablemente' => 0.85, 'probablemente no' => 0.4, 'no' => 0.1];
        $nearNo = ['no' => 1.0, 'probablemente no' => 0.85, 'probablemente' => 0.4, 'sí' => 0.1];
        if ($expected === 'sí') return $nearYes[$given] ?? 0.5;
        if ($expected === 'no') return $nearNo[$given] ?? 0.5;
        if ($expected === 'probablemente') {
            return match ($given) {
                'sí' => 0.85,
                'probablemente' => 1.0,
                'no lo sé' => 0.7,
                'probablemente no' => 0.5,
                'no' => 0.3,
                default => 0.6,
            };
        }
        if ($expected === 'probablemente no') {
            return match ($given) {
                'no' => 0.85,
                'probablemente no' => 1.0,
                'no lo sé' => 0.7,
                'probablemente' => 0.5,
                'sí' => 0.3,
                default => 0.6,
            };
        }
        // Para categorías de opción múltiple (p.ej. 'saiyan', 'angel', etc.), penalizar más los desaciertos.
        return 0.1;
    }

    public function computeProbabilities(array $asked, array $personajes, array $mapping): array
    {
        $probs = [];
        $epsilon = 1e-9;
        foreach ($personajes as $pid => $_) {
            $prob = 1.0;
            foreach ($asked as $qid => $ans) {
                $expected = $mapping[$pid][$qid] ?? 'no lo sé';
                $prob *= $this->compatibility($expected, $ans);
            }
            $probs[$pid] = max($prob, $epsilon);
        }
        // Normalizar
        $sum = array_sum($probs);
        if ($sum > 0) {
            foreach ($probs as $pid => $p) {
                $probs[$pid] = $p / $sum;
            }
        }
        return $probs;
    }

    public function selectNextQuestion(array $askedIds, array $probs, array $mapping, array $preguntas): ?int
    {
        $allQIds = array_keys($preguntas);
        $remaining = array_values(array_diff($allQIds, $askedIds));
        if (empty($remaining)) return null;
        $bestQ = null;
        $bestH = -1.0;
        foreach ($remaining as $qid) {
            $q = $preguntas[$qid] ?? null;
            if (!$q) continue;
            $answers = ['no lo sé'];
            if (($q['tipo'] ?? '') === 'multiple_choice') {
                $answers = array_merge((array)($q['opciones'] ?? []), ['no lo sé']);
            } else {
                $answers = ['sí', 'no', 'probablemente', 'probablemente no', 'no lo sé'];
            }
            $dist = array_fill_keys($answers, 0.0);
            foreach ($probs as $pid => $p) {
                $exp = $mapping[$pid][$qid] ?? 'no lo sé';
                if (!array_key_exists($exp, $dist)) {
                    // Mapear cualquier respuesta fuera de opciones a 'no lo sé'
                    $dist['no lo sé'] += $p;
                } else {
                    $dist[$exp] += $p;
                }
            }
            $total = array_sum($dist);
            if ($total <= 0) continue;
            $H = 0.0;
            foreach ($dist as $v) {
                if ($v > 0) {
                    $H += - ($v / $total) * log($v / $total, 2);
                }
            }
            if ($H > $bestH) {
                $bestH = $H;
                $bestQ = $qid;
            }
        }
        return $bestQ;
    }
}

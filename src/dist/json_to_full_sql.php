<?php
// Genera un SQL completo (schema + inserts) a partir de src/models/data.json
// Incluye tablas: personajes, preguntas, personaje_pregunta, partidas, respuestas_partida
// Uso: php src/dist/json_to_full_sql.php

declare(strict_types=1);

$jsonPath = __DIR__ . '/../models/data.json';
if (!file_exists($jsonPath)) {
    fwrite(STDERR, "No se encuentra src/models/data.json\n");
    exit(1);
}
$content = file_get_contents($jsonPath);
if ($content === false) {
    fwrite(STDERR, "No se pudo leer src/models/data.json\n");
    exit(1);
}

// Extrae múltiples objetos JSON concatenados y devuelve un array de objetos
function extractJsonObjects(string $s): array {
    $objs = [];
    $len = strlen($s);
    $i = 0;
    while ($i < $len) {
        $start = strpos($s, '{', $i);
        if ($start === false) break;
        $depth = 0; $inString = false; $escape = false; $end = null;
        for ($j = $start; $j < $len; $j++) {
            $ch = $s[$j];
            if ($inString) {
                if ($escape) { $escape = false; }
                else {
                    if ($ch === '\\') $escape = true;
                    elseif ($ch === '"') $inString = false;
                }
                continue;
            }
            if ($ch === '"') { $inString = true; continue; }
            if ($ch === '{' || $ch === '[') $depth++;
            elseif ($ch === '}' || $ch === ']') {
                $depth--; if ($depth === 0) { $end = $j; break; }
            }
        }
        if ($end === null) break;
        $jsonStr = substr($s, $start, $end - $start + 1);
        $obj = json_decode($jsonStr, true);
        if (is_array($obj)) $objs[] = $obj;
        $i = $end + 1;
    }
    return $objs;
}

function esc(string $v): string {
    $v = str_replace(["\\", "'", "\r", "\n"], ["\\\\", "\\'", ' ', ' '], $v);
    return $v;
}

$objects = extractJsonObjects($content);
if (empty($objects)) {
    // Puede ser un único objeto bien formado
    $one = json_decode($content, true);
    if (is_array($one)) $objects = [$one];
}
if (empty($objects)) {
    fwrite(STDERR, "No se pudieron decodificar objetos JSON desde data.json\n");
    exit(1);
}

// Recolectar items
$items = [];
foreach ($objects as $obj) {
    if (isset($obj['items']) && is_array($obj['items'])) {
        foreach ($obj['items'] as $it) { $items[] = $it; }
    }
}
if (empty($items)) {
    fwrite(STDERR, "No se encontraron elementos en 'items' dentro del JSON\n");
    exit(1);
}

// Conjuntos de valores distintos
// Recolectar valores y contadores de frecuencia
$racesAll = []; $gendersAll = []; $affiliationsAll = [];
foreach ($items as $it) {
    if (isset($it['race'])) $racesAll[] = trim((string)$it['race']);
    if (isset($it['gender'])) $gendersAll[] = trim((string)$it['gender']);
    if (isset($it['affiliation'])) $affiliationsAll[] = trim((string)$it['affiliation']);
}
function counts(array $arr): array { $c = []; foreach ($arr as $v) { if ($v === '') continue; $c[$v] = ($c[$v] ?? 0) + 1; } return $c; }
$raceCounts = counts($racesAll);
$genderCounts = counts($gendersAll);
$affCounts = counts($affiliationsAll);

// Valores distintos
$races = array_values(array_unique($racesAll));
$genders = array_values(array_unique($gendersAll));
$affiliations = array_values(array_unique($affiliationsAll));

// Limitar opciones a máximo 5 (top 4 por frecuencia + 'Other' si hay más)
function capOptionsToFive(array $values, array $freqs): array {
    $values = array_values(array_filter($values, fn($v) => $v !== ''));
    if (count($values) <= 5) return $values;
    // ordenar por frecuencia desc
    usort($values, function($a, $b) use ($freqs) {
        return ($freqs[$b] ?? 0) <=> ($freqs[$a] ?? 0);
    });
    $top = array_slice($values, 0, 4);
    // evitar duplicados y añadir 'Other' como última opción
    $out = array_values(array_unique($top));
    if (!in_array('Other', $out, true)) { $out[] = 'Other'; }
    return $out;
}

$racesCapped = capOptionsToFive($races, $raceCounts);
$affiliationsCapped = capOptionsToFive($affiliations, $affCounts);

// Construir preguntas tipo sí/no, creativas y diferenciadoras (predicados)
$questions = [];
$qid = 1;

// Top razas por frecuencia: crear predicados "¿Pertenece a la raza X?"
foreach ($racesCapped as $raceVal) {
    $text = "¿Pertenece a la raza {$raceVal}?";
    $questions[] = ['id' => $qid++, 'type' => 'yes_no', 'value' => 'race_equals', 'equals' => $raceVal, 'text' => $text];
}

// Género: una pregunta sí/no
$questions[] = ['id' => $qid++, 'type' => 'yes_no', 'value' => 'gender_equals', 'equals' => 'Female', 'text' => '¿Es mujer?'];

// Afiliaciones principales: predicados "¿Forma parte de X?"
foreach ($affiliationsCapped as $affVal) {
    $text = "¿Forma parte de {$affVal}?";
    $questions[] = ['id' => $qid++, 'type' => 'yes_no', 'value' => 'affiliation_equals', 'equals' => $affVal, 'text' => $text];
}

// Compuesto: ¿Es Ángel o Dios de la Destrucción?
$questions[] = ['id' => $qid++, 'type' => 'yes_no', 'value' => 'race_in', 'in' => ['Angel','God'], 'text' => '¿Es un Ángel o un Dios de la Destrucción?'];

// Generar SQL
$outPath = __DIR__ . '/dragonball_akinator_full_seed.sql';
$fh = fopen($outPath, 'w');
if (!$fh) { fwrite(STDERR, "No se pudo abrir $outPath para escritura\n"); exit(1); }

fwrite($fh, "-- Esquema y datos para juego tipo Akinator (Dragon Ball)\n");
fwrite($fh, "-- Generado automáticamente desde src/models/data.json\n\n");
fwrite($fh, "USE akinator;\n\n");

// Drops
fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
fwrite($fh, "DROP TABLE IF EXISTS respuestas_partida;\n");
fwrite($fh, "DROP TABLE IF EXISTS personaje_pregunta;\n");
fwrite($fh, "DROP TABLE IF EXISTS preguntas;\n");
fwrite($fh, "DROP TABLE IF EXISTS partidas;\n");
fwrite($fh, "DROP TABLE IF EXISTS personajes;\n");
fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n\n");

// personajes
fwrite($fh, "CREATE TABLE personajes (\n" .
    "  id INT PRIMARY KEY,\n" .
    "  nombre VARCHAR(255) NOT NULL,\n" .
    "  descripcion TEXT,\n" .
    "  imagen_url VARCHAR(512),\n" .
    "  race VARCHAR(64),\n" .
    "  gender VARCHAR(32),\n" .
    "  affiliation VARCHAR(128),\n" .
    "  ki VARCHAR(64),\n" .
    "  maxKi VARCHAR(64)\n" .
" ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n");

// preguntas
fwrite($fh, "CREATE TABLE preguntas (\n" .
    "  id INT PRIMARY KEY,\n" .
    "  texto_pregunta VARCHAR(255) NOT NULL,\n" .
    "  tipo VARCHAR(32) NOT NULL,\n" .
    "  opciones_json TEXT NULL\n" .
" ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n");

// personaje_pregunta
fwrite($fh, "CREATE TABLE personaje_pregunta (\n" .
    "  personaje_id INT NOT NULL,\n" .
    "  pregunta_id INT NOT NULL,\n" .
    "  respuesta_esperada VARCHAR(128) NOT NULL,\n" .
    "  PRIMARY KEY (personaje_id, pregunta_id),\n" .
    "  CONSTRAINT fk_pp_personaje FOREIGN KEY (personaje_id) REFERENCES personajes(id) ON DELETE CASCADE,\n" .
    "  CONSTRAINT fk_pp_pregunta FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE\n" .
" ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n");

// partidas
fwrite($fh, "CREATE TABLE partidas (\n" .
    "  id INT AUTO_INCREMENT PRIMARY KEY,\n" .
    "  usuario_id INT NULL,\n" .
    "  personaje_objetivo_id INT NULL,\n" .
    "  estado ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',\n" .
    "  estado_json JSON NULL,\n" .
    "  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n" .
" ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n");

// respuestas_partida
fwrite($fh, "CREATE TABLE respuestas_partida (\n" .
    "  id INT AUTO_INCREMENT PRIMARY KEY,\n" .
    "  partida_id INT NOT NULL,\n" .
    "  pregunta_id INT NOT NULL,\n" .
    "  respuesta_usuario VARCHAR(128) NOT NULL,\n" .
    "  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n" .
    "  CONSTRAINT fk_rp_partida FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,\n" .
    "  CONSTRAINT fk_rp_pregunta FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE\n" .
" ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n");

// INSERT personajes
$rows = [];
foreach ($items as $it) {
    $id = (int)($it['id'] ?? 0);
    if ($id <= 0) continue;
    $nombre = esc((string)($it['name'] ?? ''));
    $desc = esc((string)($it['description'] ?? ''));
    $img = esc((string)($it['image'] ?? ''));
    $race = esc(trim((string)($it['race'] ?? '')));
    $gender = esc(trim((string)($it['gender'] ?? '')));
    $aff = esc(trim((string)($it['affiliation'] ?? '')));
    $ki = esc(trim((string)($it['ki'] ?? '')));
    $maxKi = esc(trim((string)($it['maxKi'] ?? '')));
    $rows[] = sprintf("(%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
        $id, $nombre, $desc, $img, $race, $gender, $aff, $ki, $maxKi
    );
}
if (!empty($rows)) {
    fwrite($fh, "INSERT INTO personajes (id, nombre, descripcion, imagen_url, race, gender, affiliation, ki, maxKi) VALUES\n" .
        implode(",\n", $rows) . ";\n\n");
}

// INSERT preguntas (incluye tipo y opciones_json)
$qRows = [];
foreach ($questions as $q) {
    $opts = isset($q['options']) ? json_encode(array_values($q['options']), JSON_UNESCAPED_UNICODE) : null;
    $optsEsc = $opts !== null ? esc($opts) : '';
    $tipo = esc((string)$q['type']);
    $qRows[] = sprintf("(%d, '%s', '%s', %s)", (int)$q['id'], esc($q['text']), $tipo, $opts !== null ? "'{$optsEsc}'" : 'NULL');
}
if (!empty($qRows)) {
    fwrite($fh, "INSERT INTO preguntas (id, texto_pregunta, tipo, opciones_json) VALUES\n" . implode(",\n", $qRows) . ";\n\n");
}

// INSERT personaje_pregunta (respuesta esperada sí/no/no lo sé por predicado)
$ppRows = [];
foreach ($items as $it) {
    $pid = (int)($it['id'] ?? 0); if ($pid <= 0) continue;
    $race = trim((string)($it['race'] ?? ''));
    $gender = trim((string)($it['gender'] ?? ''));
    $aff = trim((string)($it['affiliation'] ?? ''));
    foreach ($questions as $q) {
        $exp = 'no lo sé';
        if ($q['value'] === 'race_equals') {
            if ($race === '') { $exp = 'no lo sé'; }
            else { $exp = ($race === (string)($q['equals'] ?? '')) ? 'sí' : 'no'; }
        } elseif ($q['value'] === 'gender_equals') {
            if ($gender === '') { $exp = 'no lo sé'; }
            else { $exp = ($gender === (string)($q['equals'] ?? '')) ? 'sí' : 'no'; }
        } elseif ($q['value'] === 'affiliation_equals') {
            if ($aff === '') { $exp = 'no lo sé'; }
            else { $exp = ($aff === (string)($q['equals'] ?? '')) ? 'sí' : 'no'; }
        } elseif ($q['value'] === 'race_in') {
            $set = (array)($q['in'] ?? []);
            if ($race === '') { $exp = 'no lo sé'; }
            else { $exp = in_array($race, $set, true) ? 'sí' : 'no'; }
        }
        $ppRows[] = sprintf("(%d, %d, '%s')", $pid, (int)$q['id'], esc($exp));
    }
}
if (!empty($ppRows)) {
    // Para evitar líneas gigantes, trocear en bloques
    $chunkSize = 500;
    for ($i = 0; $i < count($ppRows); $i += $chunkSize) {
        $chunk = array_slice($ppRows, $i, $chunkSize);
        fwrite($fh, "INSERT INTO personaje_pregunta (personaje_id, pregunta_id, respuesta_esperada) VALUES\n" . implode(",\n", $chunk) . ";\n\n");
    }
}

fclose($fh);
echo "Generado: " . $outPath . "\n";
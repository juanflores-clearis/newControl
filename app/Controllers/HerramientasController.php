<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\TechDetectorService;

require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Services/TechDetectorService.php';

class HerramientasController
{
    public function index()
    {
        Auth::requireLogin();
        View::render('herramientas/index');
    }

    public function detectorTecnologia()
    {
        // Acceso público — no requiere login
        View::render('herramientas/detector-tecnologia');
    }

    public function testWeb()
    {
        Auth::requireLogin();
        View::render('herramientas/test-web');
    }

    // =========================================================================
    //  ANÁLISIS AJAX (URL individual)
    // =========================================================================

    public function detectarAjax()
    {
        // Acceso público — no requiere login
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $url   = $input['url'] ?? '';

        if (empty($url)) {
            echo json_encode(['success' => false, 'error' => 'La URL es obligatoria']);
            return;
        }

        $service = new TechDetectorService();
        echo json_encode($service->analyze($url));
    }

    // =========================================================================
    //  SUBIDA DE FICHERO — devuelve URLs + fileId temporal
    // =========================================================================

    /**
     * Recibe un CSV/Excel, extrae las URLs y guarda todas las filas en un fichero
     * temporal para poder escribir los resultados de vuelta al fichero original.
     */
    public function detectarCsv()
    {
        // Acceso público — no requiere login
        header('Content-Type: application/json');

        if (empty($_FILES['file']['tmp_name'])) {
            echo json_encode(['success' => false, 'error' => 'No se recibió ningún fichero']);
            return;
        }

        $file    = $_FILES['file'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $tmpPath = $file['tmp_name'];

        try {
            if ($ext === 'csv') {
                $rows = $this->parseCsvRows($tmpPath);
            } elseif (in_array($ext, ['xlsx', 'xls'])) {
                $rows = $this->parseExcelRows($tmpPath, $ext);
            } else {
                echo json_encode(['success' => false, 'error' => 'Formato no soportado. Use CSV, XLSX o XLS.']);
                return;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al procesar el fichero: ' . $e->getMessage()]);
            return;
        }

        // startRow: fila 1-based donde empiezan los datos (0 = auto-detectar)
        $startRow  = max(0, (int)($_POST['startRow'] ?? 0));
        $fromIndex = $startRow > 0 ? $startRow - 1 : 0; // convertir a índice 0-based

        $urlColumn   = strtoupper(trim((string)($_POST['urlColumn'] ?? '')));
        $urlColIndex = $urlColumn !== '' ? $this->colLetterToIndex($urlColumn) : null;

        [$urls, $urlRowMap] = $this->extractUrlsFromRows($rows, $fromIndex, $urlColIndex);

        if (empty($urls)) {
            echo json_encode(['success' => false, 'error' => 'No se encontraron URLs válidas en el fichero']);
            return;
        }

        // Guardar datos originales en fichero temporal (para escritura de resultados)
        $fileId   = bin2hex(random_bytes(8));
        $origName = pathinfo($file['name'], PATHINFO_FILENAME);

        file_put_contents(
            sys_get_temp_dir() . '/nc_bulk_' . $fileId . '.json',
            json_encode([
                'rows'      => $rows,
                'urlRowMap' => $urlRowMap,
                'ext'       => $ext,
                'origName'  => $origName,
                'startRow'  => $startRow,   // 1-based; 0 = auto
            ])
        );

        echo json_encode([
            'success'  => true,
            'urls'     => $urls,
            'total'    => count($urls),
            'fileId'   => $fileId,
            'fileExt'  => $ext,
            'origName' => $origName,
        ]);
    }

    public function auditWebAjax()
    {
        Auth::requireLogin();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $url = trim((string)($input['url'] ?? ''));
        $maxPages = max(1, min(25, (int)($input['maxPages'] ?? 10)));

        if ($url === '') {
            echo json_encode(['success' => false, 'error' => 'La URL es obligatoria']);
            return;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => 'La URL no tiene un formato vÃ¡lido']);
            return;
        }

        $projectRoot = realpath(__DIR__ . '/../../');
        $scriptPath = $projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'web_audit_runner.js';

        if (!$projectRoot || !file_exists($scriptPath)) {
            echo json_encode(['success' => false, 'error' => 'No se encontrÃ³ el script de auditorÃ­a web']);
            return;
        }

        $tempInput = tempnam(sys_get_temp_dir(), 'nc_web_audit_');
        if ($tempInput === false) {
            echo json_encode(['success' => false, 'error' => 'No se pudo crear el fichero temporal de trabajo']);
            return;
        }

        file_put_contents($tempInput, json_encode([
            'url' => $url,
            'maxPages' => $maxPages,
        ], JSON_UNESCAPED_SLASHES));

        $command = 'cd ' . escapeshellarg($projectRoot)
            . ' && node ' . escapeshellarg('scripts/web_audit_runner.js')
            . ' ' . escapeshellarg($tempInput) . ' 2>&1';

        $rawOutput = shell_exec($command);
        @unlink($tempInput);

        if ($rawOutput === null) {
            echo json_encode(['success' => false, 'error' => 'No se pudo ejecutar la auditorÃ­a web desde el servidor']);
            return;
        }

        $result = json_decode($rawOutput, true);
        if (!is_array($result)) {
            echo json_encode([
                'success' => false,
                'error' => 'La auditorÃ­a devolviÃ³ una respuesta no vÃ¡lida',
                'raw' => trim($rawOutput),
            ]);
            return;
        }

        echo json_encode($result);
    }

    public function auditWebServiceAjax()
    {
        Auth::requireLogin();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $url = trim((string)($input['url'] ?? ''));
        $maxPages = max(1, min(25, (int)($input['maxPages'] ?? 10)));

        if ($url === '') {
            echo json_encode(['success' => false, 'error' => 'La URL es obligatoria']);
            return;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => 'La URL no tiene un formato valido']);
            return;
        }

        if (!function_exists('curl_init')) {
            echo json_encode([
                'success' => false,
                'error' => 'PHP no tiene cURL habilitado y no puede conectar con el servicio de auditoria web.',
            ]);
            return;
        }

        $serviceUrl = rtrim((string)($_ENV['WEB_AUDIT_SERVICE_URL'] ?? 'http://127.0.0.1:3100'), '/');
        $endpoint = $serviceUrl . '/audit';
        $payload = json_encode([
            'url' => $url,
            'maxPages' => $maxPages,
        ], JSON_UNESCAPED_SLASHES);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 180,
        ]);

        $rawOutput = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawOutput === false || $rawOutput === null) {
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo conectar con el servicio de auditoria web.',
                'details' => $curlError !== '' ? $curlError : ('Endpoint: ' . $endpoint),
            ]);
            return;
        }

        $result = json_decode($rawOutput, true);
        if (!is_array($result)) {
            echo json_encode([
                'success' => false,
                'error' => 'La auditoria devolvio una respuesta no valida',
                'http_code' => $httpCode,
                'raw' => trim($rawOutput),
            ]);
            return;
        }

        echo json_encode($result);
    }

    // =========================================================================
    //  EXPORTACIÓN — escribe resultados en fichero original o genera uno nuevo
    // =========================================================================

    /**
     * Recibe los resultados del análisis masivo y:
     *   - Si se especificó columna: escribe resultados en el fichero original a
     *     partir de esa columna y devuelve el fichero modificado.
     *   - Si no hay columna: genera un fichero nuevo (CSV o Excel) con los resultados.
     *
     * Devuelve el fichero directamente como descarga binaria.
     */
    public function exportarResultados()
    {
        $input   = json_decode(file_get_contents('php://input'), true) ?? [];
        $fileId  = preg_replace('/[^a-f0-9]/', '', $input['fileId'] ?? '');
        $column  = strtoupper(trim($input['column']  ?? ''));
        $format  = in_array($input['format'] ?? '', ['xlsx', 'csv']) ? $input['format'] : 'csv';
        $insertHeaderRow = !empty($input['insertHeaderRow']);
        $results = $input['results'] ?? [];

        // ── Definición canónica de columnas exportables ───────────────────────
        // Clave → etiqueta de cabecera
        $allCols = [
            'url'        => 'URL',
            'topTech'    => 'Tecnología principal',
            'confidence' => 'Confianza (%)',
            'others'     => 'Otras tecnologías',
            'evidence'   => 'Evidencias',
            'status'     => 'Estado',
        ];

        // Validar la selección del usuario (preservar orden, eliminar claves desconocidas)
        $requested = $input['columns'] ?? array_keys($allCols);
        $selected  = array_values(array_filter($requested, fn($c) => isset($allCols[$c])));
        if (empty($selected)) $selected = array_keys($allCols);

        // ── MODO A: escribir resultados en el fichero original ────────────────
        if ($column !== '' && $fileId !== '') {
            $tempPath = sys_get_temp_dir() . '/nc_bulk_' . $fileId . '.json';

            if (!file_exists($tempPath)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Fichero temporal no encontrado o expirado. Vuelve a subir el fichero.']);
                return;
            }

            $tempData  = json_decode(file_get_contents($tempPath), true);
            $rows      = $tempData['rows']      ?? [];
            $urlRowMap = $tempData['urlRowMap'] ?? [];
            $ext       = $tempData['ext']       ?? 'csv';
            $origName  = $tempData['origName']  ?? 'resultados';
            $colIndex  = $this->colLetterToIndex($column);

            // En modo escritura, la URL ya existe en el fichero original → excluirla
            $writeCols   = array_values(array_filter($selected, fn($c) => $c !== 'url'));
            $writeLabels = array_map(fn($c) => $allCols[$c], $writeCols);
            $rowOffset   = 0;

            // Determinar en qué fila escribir los labels de cabecera
            $startRow = (int)($tempData['startRow'] ?? 0); // 1-based; 0 = auto

            if ($insertHeaderRow) {
                $headerRowIdx = $startRow > 0
                    ? max(0, $startRow - 1)
                    : (empty($urlRowMap) ? 0 : min(array_values($urlRowMap)));

                array_splice($rows, $headerRowIdx, 0, [$this->spliceColumns([], $colIndex, $writeLabels)]);
                $rowOffset = 1;
            } elseif ($startRow > 0) {
                // Explícito: la fila de cabecera es la fila inmediatamente anterior a los datos
                // startRow=2 → cabecera en índice 0; startRow=3 → cabecera en índice 1; startRow=1 → sin cabecera
                $headerRowIdx = $startRow - 2;
                if ($headerRowIdx >= 0 && !empty($rows)) {
                    $rows[$headerRowIdx] = $this->spliceColumns($rows[$headerRowIdx] ?? [], $colIndex, $writeLabels);
                }
            } else {
                // Auto-detect: si la primera URL no está en la fila 0, la fila 0 es cabecera
                $firstUrlRowIdx = empty($urlRowMap) ? 1 : min(array_values($urlRowMap));
                if ($firstUrlRowIdx > 0 && !empty($rows)) {
                    $rows[0] = $this->spliceColumns($rows[0], $colIndex, $writeLabels);
                }
            }

            // Escribir resultados en cada fila con URL
            foreach ($results as $r) {
                $rowIdx = $urlRowMap[$r['url'] ?? ''] ?? null;
                if ($rowIdx === null) continue;
                $cellValues = array_map(fn($c) => (string)($r[$c] ?? ''), $writeCols);
                $targetRowIdx = $rowIdx + $rowOffset;
                $rows[$targetRowIdx] = $this->spliceColumns($rows[$targetRowIdx] ?? [], $colIndex, $cellValues);
            }

            $downloadName = $origName . '_resultados';

            if ($ext === 'xlsx') {
                $tmpFile = $this->generateXlsx($rows);
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $downloadName . '.xlsx"');
                readfile($tmpFile);
                unlink($tmpFile);
            } else {
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $downloadName . '.csv"');
                $this->outputCsv($rows);
            }

            return;
        }

        // ── MODO B: generar fichero nuevo con columnas seleccionadas ──────────
        $rows   = [];
        $rows[] = array_map(fn($c) => $allCols[$c], $selected); // cabecera

        foreach ($results as $r) {
            $rows[] = array_map(fn($c) => (string)($r[$c] ?? ''), $selected);
        }

        $today = date('Y-m-d');

        if ($format === 'xlsx') {
            $tmpFile = $this->generateXlsx($rows);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="deteccion-tecnologias-' . $today . '.xlsx"');
            readfile($tmpFile);
            unlink($tmpFile);
        } else {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="deteccion-tecnologias-' . $today . '.csv"');
            $this->outputCsv($rows);
        }

    }

    // =========================================================================
    //  PARSEO DE FICHEROS
    // =========================================================================

    /** Devuelve todas las filas del CSV como array de arrays. */
    private function parseCsvRows(string $path): array
    {
        if (($handle = fopen($path, 'r')) === false) {
            throw new \Exception('No se pudo abrir el fichero CSV');
        }

        // Detectar delimitador leyendo la primera línea
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map('trim', $row);
        }
        fclose($handle);
        return $rows;
    }

    /** Devuelve todas las filas de un Excel como array de arrays. */
    private function parseExcelRows(string $path, string $ext): array
    {
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows  = [];
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[] = trim((string)$cell->getValue());
                }
                $rows[] = $cells;
            }
            return $rows;
        }

        if ($ext === 'xlsx') {
            return $this->parseXlsxRows($path);
        }

        throw new \Exception('Para leer ficheros .xls instale PhpSpreadsheet (composer require phpoffice/phpspreadsheet)');
    }

    /** Lector XLSX manual (sin dependencias externas). */
    private function parseXlsxRows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \Exception('No se pudo abrir el fichero XLSX');
        }

        // Strings compartidos
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) $text .= (string)$r->t;
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) throw new \Exception('No se encontró la hoja de datos en el XLSX');

        $sheet = simplexml_load_string($sheetXml);
        $rows  = [];

        foreach ($sheet->sheetData->row as $xmlRow) {
            $rowIdx  = (int)$xmlRow['r'] - 1;
            $cells   = [];
            $maxCol  = 0;

            foreach ($xmlRow->c as $cell) {
                $colRef = preg_replace('/[0-9]/', '', (string)$cell['r']);
                $colIdx = $this->colLetterToIndex($colRef);
                $maxCol = max($maxCol, $colIdx);

                $type = (string)($cell['t'] ?? '');
                $val  = (string)$cell->v;

                if ($type === 's')          $val = $sharedStrings[(int)$val] ?? '';
                elseif ($type === 'inlineStr') $val = (string)$cell->is->t;

                $cells[$colIdx] = trim($val);
            }

            $fullRow = [];
            for ($i = 0; $i <= $maxCol; $i++) $fullRow[] = $cells[$i] ?? '';
            $rows[$rowIdx] = $fullRow;
        }

        return array_values($rows);
    }

    /**
     * Recorre todas las filas a partir de $fromIndex y devuelve:
     *   - $urls      : lista de URLs en orden (sin duplicados, primera aparición)
     *   - $urlRowMap : [ url => rowIndex ]
     *
     * $fromIndex es el índice de fila 0-based desde el que empezar a buscar URLs.
     * Si es 0 (por defecto) se recorren todas las filas.
     */
    private function extractUrlsFromRows(array $rows, int $fromIndex = 0, ?int $urlColIndex = null): array
    {
        $urls      = [];
        $urlRowMap = [];

        foreach ($rows as $rowIdx => $row) {
            if ($rowIdx < $fromIndex) continue; // saltar filas de cabecera/título
            if ($urlColIndex !== null) {
                $cell = trim((string)($row[$urlColIndex] ?? ''));
                if ($this->looksLikeUrl($cell) && !isset($urlRowMap[$cell])) {
                    $urls[]           = $cell;
                    $urlRowMap[$cell] = $rowIdx;
                }
                continue;
            }
            foreach ($row as $cell) {
                $cell = trim($cell);
                if ($this->looksLikeUrl($cell) && !isset($urlRowMap[$cell])) {
                    $urls[]           = $cell;
                    $urlRowMap[$cell] = $rowIdx;
                    break;
                }
            }
        }

        return [$urls, $urlRowMap];
    }

    // =========================================================================
    //  GENERACIÓN DE FICHEROS DE SALIDA
    // =========================================================================

    /**
     * Genera un fichero XLSX mínimo válido (sin dependencias externas).
     * Devuelve la ruta del fichero temporal.
     */
    private function generateXlsx(array $rows): string
    {
        $xmlRows = '';
        foreach ($rows as $rowIdx => $row) {
            $rowNum   = $rowIdx + 1;
            $xmlCells = '';
            foreach ($row as $colIdx => $value) {
                $ref     = $this->indexToColLetter($colIdx) . $rowNum;
                $escaped = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xmlCells .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$escaped}</t></is></c>";
            }
            $xmlRows .= "<row r=\"{$rowNum}\">{$xmlCells}</row>";
        }

        $ns      = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "<worksheet xmlns=\"{$ns}\"><sheetData>{$xmlRows}</sheetData></worksheet>";

        $ns2     = 'http://schemas.openxmlformats.org/package/2006/content-types';
        $ns3     = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $ns4     = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $tmpFile = tempnam(sys_get_temp_dir(), 'nc_xlsx_');
        $zip     = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "<Types xmlns=\"{$ns2}\">"
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>'
        );

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "<Relationships xmlns=\"{$ns3}\">"
            . "<Relationship Id=\"rId1\" Type=\"{$ns4}/officeDocument\" Target=\"xl/workbook.xml\"/>"
            . '</Relationships>'
        );

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Hoja1" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>'
        );

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "<Relationships xmlns=\"{$ns3}\">"
            . "<Relationship Id=\"rId1\" Type=\"{$ns4}/worksheet\" Target=\"worksheets/sheet1.xml\"/>"
            . '</Relationships>'
        );

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $tmpFile;
    }

    /** Escribe filas en CSV con BOM UTF-8 a la salida estándar. */
    private function outputCsv(array $rows): void
    {
        echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
        $fp = fopen('php://output', 'w');
        foreach ($rows as $row) fputcsv($fp, $row);
        fclose($fp);
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    /**
     * Rellena $row hasta $colIndex con vacíos y luego reemplaza todo lo que
     * había a partir de $colIndex con $newCols.
     */
    private function spliceColumns(array $row, int $colIndex, array $newCols): array
    {
        while (count($row) < $colIndex) $row[] = '';
        array_splice($row, $colIndex, count($row) - $colIndex, $newCols);
        return $row;
    }

    /** "C" → 2, "AA" → 26, etc. (0-indexed) */
    private function colLetterToIndex(string $col): int
    {
        $col    = strtoupper(trim($col));
        $result = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $result = $result * 26 + (ord($col[$i]) - 64);
        }
        return max(0, $result - 1);
    }

    /** 0 → "A", 25 → "Z", 26 → "AA", etc. */
    private function indexToColLetter(int $index): string
    {
        $letter = '';
        $n      = $index + 1;
        while ($n > 0) {
            $rem    = ($n - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $n      = intdiv($n - 1, 26);
        }
        return $letter;
    }

    private function looksLikeUrl(string $value): bool
    {
        if (empty($value)) return false;
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            || preg_match('/^([a-z0-9\-]+\.)+[a-z]{2,}(\/.*)?$/i', $value);
    }
}

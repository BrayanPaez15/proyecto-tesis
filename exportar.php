<?php
/**
 * Exportacion de registros a Excel por rango de fechas.
 *
 * Genera un archivo .xls (tabla HTML interpretada por Excel) con los
 * registros de un modulo cuya fecha_registro este entre "desde" y
 * "hasta" (parametros GET), validando el modulo contra la lista blanca
 * de includes/modulos.php para evitar acceso a tablas arbitrarias.
 * Requiere sesion activa y rol de administrador.
 */
require "auth.php";
include "conexion.php";
require_once "includes/roles.php";
require_admin();

$modulos = require "includes/modulos.php";

$modulo = $_GET['modulo'] ?? '';
if (!isset($modulos[$modulo])) {
    http_response_code(400);
    exit('Módulo no válido.');
}

$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    http_response_code(400);
    exit('Rango de fechas no válido.');
}

$tabla = $modulos[$modulo]['tabla'];
$columnas = $modulos[$modulo]['columnas'];

$stmt = $conn->prepare("SELECT * FROM `$tabla` WHERE fecha_registro >= ? AND fecha_registro < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY fecha_registro");
$stmt->bind_param("ss", $desde, $hasta);
$stmt->execute();
$resultado = $stmt->get_result();

$nombreArchivo = $modulo . '_' . $desde . '_a_' . $hasta . '.xls';

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");

echo "\xEF\xBB\xBF";
echo "<table border='1'>";
echo "<tr>";
foreach ($columnas as $etiqueta) {
    echo "<th>" . htmlspecialchars($etiqueta) . "</th>";
}
echo "</tr>";

while ($fila = $resultado->fetch_assoc()) {
    echo "<tr>";
    foreach (array_keys($columnas) as $campo) {
        // mso-number-format "\@": Excel trata la celda como texto, de modo que un dato que empiece por
        // =, +, - o @ no se ejecuta como formula (inyeccion de formulas).
        echo "<td style=\"mso-number-format:'\\@'\">" . htmlspecialchars($fila[$campo] ?? '') . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

$stmt->close();

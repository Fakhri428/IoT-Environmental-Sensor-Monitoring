<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$sql = "SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Belum ada data sensor"
    ]);
    exit;
}

$sensor = $result->fetch_assoc();

$inputData = [
    "temperature" => (float) $sensor["temperature"],
    "humidity" => (float) $sensor["humidity"],
    "co" => (float) $sensor["co"],
    "lpg" => (float) $sensor["lpg"],
    "smoke" => (float) $sensor["smoke"],
    "light_intensity" => (float) $sensor["light_intensity"],
    "motion_status" => (int) $sensor["motion_status"]
];

$jsonInput = json_encode($inputData);
$encodedInput = base64_encode($jsonInput);

$projectRoot = realpath(__DIR__ . "/..");

$scriptPath = $projectRoot . "/ai_model/predict.py";
$pythonCandidates = [
    $projectRoot . "/venv/Scripts/python.exe",
    $projectRoot . "/venv/bin/python",
    $projectRoot . "/venv/bin/python3"
];
$pythonPath = null;

foreach ($pythonCandidates as $candidate) {
    if (file_exists($candidate)) {
        $pythonPath = $candidate;
        break;
    }
}

if ($pythonPath === null) {
    echo json_encode([
        "status" => "error",
        "message" => "Python virtual environment tidak ditemukan",
        "checked_paths" => $pythonCandidates
    ]);
    exit;
}

if (!file_exists($scriptPath)) {
    echo json_encode([
        "status" => "error",
        "message" => "File predict.py tidak ditemukan di: " . $scriptPath
    ]);
    exit;
}

$command = escapeshellarg($pythonPath) . " " .
           escapeshellarg($scriptPath) . " " .
           escapeshellarg($encodedInput);

$output = shell_exec($command);

if ($output === null || trim($output) === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Python script tidak menghasilkan output."
    ]);
    exit;
}

$prediction = json_decode($output, true);

if (!$prediction) {
    echo json_encode([
        "status" => "error",
        "message" => "Output Python tidak valid.",
        "raw_output" => $output
    ]);
    exit;
}

if ($prediction["status"] !== "success") {
    echo json_encode($prediction);
    exit;
}

$predictionLabel = $prediction["prediction_label"];
$predictionScore = (float) $prediction["prediction_score"];
$sensorId = (int) $sensor["id"];

$predictionReason = "Model Random Forest memprediksi status " . $predictionLabel .
    " berdasarkan fitur temperature, humidity, CO, LPG, smoke, light intensity, dan motion status.";

$stmt = $conn->prepare("
    UPDATE sensor_data
    SET prediction_label = ?, prediction_score = ?, prediction_reason = ?
    WHERE id = ?
");

$stmt->bind_param("sdsi", $predictionLabel, $predictionScore, $predictionReason, $sensorId);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Prediksi berhasil diproses menggunakan model AI dari Google Colab.",
        "prediction_label" => $predictionLabel,
        "prediction_score" => $predictionScore,
        "prediction_reason" => $predictionReason
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Gagal menyimpan hasil prediksi ke database.",
        "error" => $stmt->error
    ]);
}

$stmt->close();
$conn->close();

?>

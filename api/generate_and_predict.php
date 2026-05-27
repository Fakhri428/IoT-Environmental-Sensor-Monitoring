<?php

header("Content-Type: application/json");
require_once "../config/database.php";

/*
    1. Generate data sensor simulasi
*/

$device_id = "IOT-DEVICE-001";

$temperature = rand(250, 420) / 10;
$humidity = rand(300, 900) / 10;
$co = rand(100, 900) / 100000;
$lpg = rand(100, 1200) / 100000;
$smoke = rand(100, 900) / 1000;
$light_intensity = rand(0, 1000);
$motion_status = rand(0, 1);

/*
    2. Simpan data sensor ke MySQL
*/

$stmt = $conn->prepare("
    INSERT INTO sensor_data
    (
        device_id,
        temperature,
        humidity,
        co,
        lpg,
        smoke,
        light_intensity,
        motion_status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sdddddii",
    $device_id,
    $temperature,
    $humidity,
    $co,
    $lpg,
    $smoke,
    $light_intensity,
    $motion_status
);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Gagal menyimpan data sensor",
        "error" => $stmt->error
    ]);
    exit;
}

$sensor_id = $stmt->insert_id;
$stmt->close();

/*
    3. Siapkan data untuk model AI
*/

$inputData = [
    "temperature" => (float) $temperature,
    "humidity" => (float) $humidity,
    "co" => (float) $co,
    "lpg" => (float) $lpg,
    "smoke" => (float) $smoke,
    "light_intensity" => (float) $light_intensity,
    "motion_status" => (int) $motion_status
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
        "message" => "File predict.py tidak ditemukan",
        "path" => $scriptPath
    ]);
    exit;
}

/*
    4. Panggil model AI dari Python
*/

$command = escapeshellarg($pythonPath) . " " .
           escapeshellarg($scriptPath) . " " .
           escapeshellarg($encodedInput);

$output = shell_exec($command);

if ($output === null || trim($output) === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Python script tidak menghasilkan output"
    ]);
    exit;
}

$prediction = json_decode($output, true);

if (!$prediction) {
    echo json_encode([
        "status" => "error",
        "message" => "Output Python tidak valid",
        "raw_output" => $output
    ]);
    exit;
}

if ($prediction["status"] !== "success") {
    echo json_encode($prediction);
    exit;
}

/*
    5. Simpan hasil prediksi AI ke MySQL
*/

$prediction_label = $prediction["prediction_label"];
$prediction_score = (float) $prediction["prediction_score"];

$prediction_reason = "Model Random Forest memprediksi status " . $prediction_label .
    " berdasarkan fitur temperature, humidity, CO, LPG, smoke, light intensity, dan motion status.";

$updateStmt = $conn->prepare("
    UPDATE sensor_data
    SET prediction_label = ?, prediction_score = ?, prediction_reason = ?
    WHERE id = ?
");

$updateStmt->bind_param(
    "sdsi",
    $prediction_label,
    $prediction_score,
    $prediction_reason,
    $sensor_id
);

if (!$updateStmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Gagal menyimpan hasil prediksi",
        "error" => $updateStmt->error
    ]);
    exit;
}

$updateStmt->close();

/*
    6. Ambil data final yang sudah lengkap
*/

$finalStmt = $conn->prepare("
    SELECT * FROM sensor_data
    WHERE id = ?
    LIMIT 1
");

$finalStmt->bind_param("i", $sensor_id);
$finalStmt->execute();

$result = $finalStmt->get_result();
$finalData = $result->fetch_assoc();

$finalStmt->close();
$conn->close();

echo json_encode([
    "status" => "success",
    "message" => "Data sensor berhasil dibuat dan diprediksi secara otomatis",
    "data" => $finalData
]);

?>

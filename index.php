<?php

require_once "config/database.php";

$latestQuery = "SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1";
$latestResult = $conn->query($latestQuery);
$latest = null;

if ($latestResult && $latestResult->num_rows > 0) {
    $latest = $latestResult->fetch_assoc();
}

$historyQuery = "SELECT * FROM sensor_data ORDER BY id DESC LIMIT 20";
$historyResult = $conn->query($historyQuery);

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function valueOrDash($row, $key) {
    if (!$row || !isset($row[$key]) || $row[$key] === "") {
        return "-";
    }

    return e($row[$key]);
}

function motionText($value) {
    if ($value === null || $value === "") {
        return "-";
    }

    return (int) $value === 1 ? "DETECTED" : "NOT DETECTED";
}

function predictionBadge($label) {
    if ($label === "NORMAL") {
        return "<span class='badge badge-normal'>NORMAL</span>";
    } elseif ($label === "WARNING") {
        return "<span class='badge badge-warning'>WARNING</span>";
    } elseif ($label === "DANGER") {
        return "<span class='badge badge-danger'>DANGER</span>";
    }

    return "<span class='badge badge-empty'>BELUM DIPREDIKSI</span>";
}

function predictionCardClass($label) {
    if ($label === "NORMAL") {
        return "prediction-normal";
    } elseif ($label === "WARNING") {
        return "prediction-warning";
    } elseif ($label === "DANGER") {
        return "prediction-danger";
    }

    return "";
}

$latestPrediction = $latest["prediction_label"] ?? null;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Environmental Sensor Monitoring</title>
    <link rel="stylesheet" href="assets/style.css?v=20260527-2">
</head>
<body>

<div class="container">

    <div class="header">
        <div>
            <span class="eyebrow">IoT + Web + AI Dashboard</span>
            <h1>IoT Environmental Sensor Monitoring</h1>
            <p>
                Dashboard monitoring sensor lingkungan berbasis PHP, MySQL, dan model AI.
                Data sensor dapat dibuat dari simulasi, disimpan ke database, lalu diprediksi otomatis.
            </p>
        </div>

        <div class="header-status">
            <span>Status Prediksi</span>
            <div id="hero-prediction"><?= predictionBadge($latestPrediction); ?></div>
            <small>Score: <strong id="hero-score"><?= valueOrDash($latest, "prediction_score"); ?></strong></small>
        </div>
    </div>

    <div class="action-panel">
        <div class="action-title">
            <h2>Kontrol Sistem</h2>
            <p>Jalankan simulasi, prediksi AI, atau mode realtime setiap 30 detik.</p>
        </div>

        <div class="action-bar">
            <button onclick="generateSensorData()">Generate Data Sensor</button>
            <button class="btn-secondary" onclick="predictSensorData()">Prediksi dengan AI</button>
            <button onclick="generateAndPredictNow()">Generate + Prediksi</button>
            <button class="btn-secondary" onclick="startRealtime()">Start Realtime</button>
            <button class="btn-muted" onclick="stopRealtime()">Stop</button>
            <button class="btn-danger" onclick="window.location.reload()">Refresh</button>
        </div>
    </div>

    <div id="realtime-panel" class="card realtime-card">
        <div>
            <h3>Status Realtime</h3>
            <p id="realtime-status" class="card-text">Realtime belum aktif.</p>
        </div>
        <div class="countdown">
            <span>Generate berikutnya</span>
            <strong id="countdown">-</strong>
            <small>detik</small>
        </div>
    </div>

    <?php if (!$latest): ?>
        <div id="empty-state" class="card empty-state">
            <h3>Data Sensor</h3>
            <p class="card-text">Belum ada data sensor. Klik Generate Data Sensor atau Start Realtime untuk mulai mengisi dashboard.</p>
        </div>
    <?php endif; ?>

    <div class="cards">

        <div class="card metric-card">
            <h3>Temperature</h3>
            <p id="latest-temperature"><?= $latest ? e($latest["temperature"]) . " &deg;C" : "-"; ?></p>
        </div>

        <div class="card metric-card">
            <h3>Humidity</h3>
            <p id="latest-humidity"><?= $latest ? e($latest["humidity"]) . " %" : "-"; ?></p>
        </div>

        <div class="card metric-card">
            <h3>CO</h3>
            <p id="latest-co"><?= valueOrDash($latest, "co"); ?></p>
        </div>

        <div class="card metric-card">
            <h3>LPG</h3>
            <p id="latest-lpg"><?= valueOrDash($latest, "lpg"); ?></p>
        </div>

        <div class="card metric-card">
            <h3>Smoke</h3>
            <p id="latest-smoke"><?= valueOrDash($latest, "smoke"); ?></p>
        </div>

        <div class="card metric-card">
            <h3>Light Intensity</h3>
            <p id="latest-light"><?= valueOrDash($latest, "light_intensity"); ?></p>
        </div>

        <div class="card metric-card">
            <h3>Motion Status</h3>
            <p id="latest-motion"><?= $latest ? motionText($latest["motion_status"]) : "-"; ?></p>
        </div>

        <div id="prediction-card" class="card metric-card <?= predictionCardClass($latestPrediction); ?>">
            <h3>AI Prediction</h3>
            <p id="latest-prediction"><?= predictionBadge($latestPrediction); ?></p>
            <small>Score: <span id="latest-score"><?= valueOrDash($latest, "prediction_score"); ?></span></small>
        </div>

        <div class="card reason-card">
            <h3>Prediction Reason</h3>
            <p id="latest-reason" class="card-text">
                <?= $latest && !empty($latest["prediction_reason"]) ? e($latest["prediction_reason"]) : "Belum ada alasan prediksi."; ?>
            </p>
        </div>

    </div>

    <div class="section-title">
        <h2>Riwayat Data Sensor</h2>
        <p>Menampilkan 20 data sensor terbaru dari database.</p>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>Temp</th>
                    <th>Humidity</th>
                    <th>CO</th>
                    <th>LPG</th>
                    <th>Smoke</th>
                    <th>Light</th>
                    <th>Motion</th>
                    <th>Prediction</th>
                    <th>Score</th>
                    <th>Reason</th>
                    <th>Created At</th>
                </tr>
            </thead>

            <tbody id="sensor-history-body">
                <?php if ($historyResult && $historyResult->num_rows > 0): ?>
                    <?php while ($row = $historyResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($row["id"]); ?></td>
                            <td><?= e($row["device_id"]); ?></td>
                            <td><?= e($row["temperature"]); ?> &deg;C</td>
                            <td><?= e($row["humidity"]); ?> %</td>
                            <td><?= e($row["co"]); ?></td>
                            <td><?= e($row["lpg"]); ?></td>
                            <td><?= e($row["smoke"]); ?></td>
                            <td><?= e($row["light_intensity"]); ?></td>
                            <td><?= motionText($row["motion_status"]); ?></td>
                            <td><?= predictionBadge($row["prediction_label"]); ?></td>
                            <td><?= e($row["prediction_score"] ?? "-"); ?></td>
                            <td class="reason-cell"><?= e($row["prediction_reason"] ?? "-"); ?></td>
                            <td><?= e($row["created_at"]); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13" class="empty-table">Belum ada data sensor.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
let realtimeInterval = null;
let countdownInterval = null;
let countdownValue = 30;

function generateSensorData() {
    fetch("simulation/generate_sensor.php")
        .then(response => response.json())
        .then(data => {
            console.log(data);

            if (data.status === "success") {
                alert("Data sensor berhasil dibuat");
                window.location.reload();
            } else {
                alert("Gagal membuat data sensor: " + data.message);
            }
        })
        .catch(error => {
            console.error(error);
            alert("Terjadi error saat membuat data sensor");
        });
}

function predictSensorData() {
    fetch("api/predict_sensor.php")
        .then(response => response.json())
        .then(data => {
            console.log(data);

            if (data.status === "success") {
                alert(
                    "Hasil prediksi: " +
                    data.prediction_label +
                    "\nScore: " +
                    data.prediction_score +
                    "\nAlasan: " +
                    data.prediction_reason
                );

                window.location.reload();
            } else {
                alert("Prediksi gagal: " + data.message);
            }
        })
        .catch(error => {
            console.error(error);
            alert("Terjadi error saat memproses prediksi");
        });
}

function generateAndPredictNow() {
    fetch("api/generate_and_predict.php")
        .then(response => response.json())
        .then(data => {
            console.log(data);

            if (data.status === "success") {
                updateDashboard(data.data);
                loadSensorHistory();
            } else {
                alert("Generate dan prediksi gagal: " + data.message);
            }
        })
        .catch(error => {
            console.error(error);
            alert("Terjadi error saat generate dan prediksi");
        });
}

function startRealtime() {
    if (realtimeInterval !== null) {
        alert("Realtime sudah berjalan");
        return;
    }

    generateAndPredictNow();

    countdownValue = 30;
    updateRealtimeStatus("Realtime aktif. Data baru dibuat dan diprediksi setiap 30 detik.", "active");
    updateCountdown(countdownValue);

    realtimeInterval = setInterval(function() {
        generateAndPredictNow();
        countdownValue = 30;
        updateCountdown(countdownValue);
    }, 30000);

    countdownInterval = setInterval(function() {
        countdownValue--;

        if (countdownValue <= 0) {
            countdownValue = 30;
        }

        updateCountdown(countdownValue);
    }, 1000);
}

function stopRealtime() {
    if (realtimeInterval !== null) {
        clearInterval(realtimeInterval);
        realtimeInterval = null;
    }

    if (countdownInterval !== null) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }

    updateRealtimeStatus("Realtime berhenti.", "stopped");
    updateCountdown("-");
}

function updateRealtimeStatus(text, state) {
    const realtimeStatus = document.getElementById("realtime-status");
    const realtimePanel = document.getElementById("realtime-panel");

    if (realtimeStatus) {
        realtimeStatus.innerText = text;
    }

    if (realtimePanel) {
        realtimePanel.classList.remove("realtime-active", "realtime-stopped");

        if (state === "active") {
            realtimePanel.classList.add("realtime-active");
        } else if (state === "stopped") {
            realtimePanel.classList.add("realtime-stopped");
        }
    }
}

function updateCountdown(value) {
    const countdownElement = document.getElementById("countdown");

    if (countdownElement) {
        countdownElement.innerText = value;
    }
}

function updateDashboard(sensor) {
    const fields = {
        "latest-temperature": sensor.temperature + " \u00B0C",
        "latest-humidity": sensor.humidity + " %",
        "latest-co": sensor.co,
        "latest-lpg": sensor.lpg,
        "latest-smoke": sensor.smoke,
        "latest-light": sensor.light_intensity,
        "latest-motion": sensor.motion_status == 1 ? "DETECTED" : "NOT DETECTED",
        "latest-score": sensor.prediction_score ?? "-",
        "hero-score": sensor.prediction_score ?? "-"
    };

    Object.keys(fields).forEach(function(id) {
        const element = document.getElementById(id);

        if (element) {
            element.innerText = fields[id];
        }
    });

    const predictionElement = document.getElementById("latest-prediction");
    const heroPredictionElement = document.getElementById("hero-prediction");

    if (predictionElement) {
        predictionElement.innerHTML = getPredictionBadge(sensor.prediction_label);
    }

    if (heroPredictionElement) {
        heroPredictionElement.innerHTML = getPredictionBadge(sensor.prediction_label);
    }

    setPredictionCardState(sensor.prediction_label);

    const reasonElement = document.getElementById("latest-reason");

    if (reasonElement) {
        reasonElement.innerText = sensor.prediction_reason ?? "Belum ada alasan prediksi.";
    }

    const emptyState = document.getElementById("empty-state");

    if (emptyState) {
        emptyState.style.display = "none";
    }
}

function setPredictionCardState(label) {
    const predictionCard = document.getElementById("prediction-card");

    if (!predictionCard) {
        return;
    }

    predictionCard.classList.remove("prediction-normal", "prediction-warning", "prediction-danger");

    if (label === "NORMAL") {
        predictionCard.classList.add("prediction-normal");
    } else if (label === "WARNING") {
        predictionCard.classList.add("prediction-warning");
    } else if (label === "DANGER") {
        predictionCard.classList.add("prediction-danger");
    }
}

function loadSensorHistory() {
    fetch("api/get_sensor_history.php?limit=20")
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                renderSensorHistory(data.data);
            }
        })
        .catch(error => {
            console.error(error);
        });
}

function renderSensorHistory(rows) {
    const tbody = document.getElementById("sensor-history-body");

    if (!tbody) {
        return;
    }

    tbody.innerHTML = "";

    if (!rows || rows.length === 0) {
        tbody.innerHTML = "<tr><td colspan='13' class='empty-table'>Belum ada data sensor.</td></tr>";
        return;
    }

    rows.forEach(function(row) {
        const motionText = row.motion_status == 1 ? "DETECTED" : "NOT DETECTED";

        const tr = document.createElement("tr");

        tr.innerHTML = `
            <td>${escapeHtml(row.id)}</td>
            <td>${escapeHtml(row.device_id)}</td>
            <td>${escapeHtml(row.temperature)} \u00B0C</td>
            <td>${escapeHtml(row.humidity)} %</td>
            <td>${escapeHtml(row.co)}</td>
            <td>${escapeHtml(row.lpg)}</td>
            <td>${escapeHtml(row.smoke)}</td>
            <td>${escapeHtml(row.light_intensity)}</td>
            <td>${motionText}</td>
            <td>${getPredictionBadge(row.prediction_label)}</td>
            <td>${escapeHtml(row.prediction_score ?? "-")}</td>
            <td class="reason-cell">${escapeHtml(row.prediction_reason ?? "-")}</td>
            <td>${escapeHtml(row.created_at)}</td>
        `;

        tbody.appendChild(tr);
    });
}

function getPredictionBadge(label) {
    if (label === "NORMAL") {
        return "<span class='badge badge-normal'>NORMAL</span>";
    }

    if (label === "WARNING") {
        return "<span class='badge badge-warning'>WARNING</span>";
    }

    if (label === "DANGER") {
        return "<span class='badge badge-danger'>DANGER</span>";
    }

    return "<span class='badge badge-empty'>BELUM DIPREDIKSI</span>";
}

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return "";
    }

    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}
</script>

</body>
</html>

<?php
$conn->close();
?>

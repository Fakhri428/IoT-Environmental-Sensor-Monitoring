import sys
import json
import os
import base64
import binascii
import joblib
import pandas as pd

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

MODEL_PATH = os.path.join(BASE_DIR, "model.pkl")
SCALER_PATH = os.path.join(BASE_DIR, "scaler.pkl")
LABEL_ENCODER_PATH = os.path.join(BASE_DIR, "label_encoder.pkl")

FEATURE_COLUMNS = [
    "temperature",
    "humidity",
    "co",
    "lpg",
    "smoke",
    "light_intensity",
    "motion_status"
]

def main():
    try:
        if len(sys.argv) < 2:
            raise ValueError("Input JSON tidak ditemukan.")

        input_json = sys.argv[1]

        try:
            data = json.loads(input_json)
        except json.JSONDecodeError:
            try:
                decoded_json = base64.b64decode(input_json).decode("utf-8")
                data = json.loads(decoded_json)
            except (binascii.Error, UnicodeDecodeError, json.JSONDecodeError) as decode_error:
                raise ValueError("Input JSON tidak valid.") from decode_error

        input_data = pd.DataFrame([{
            "temperature": float(data["temperature"]),
            "humidity": float(data["humidity"]),
            "co": float(data["co"]),
            "lpg": float(data["lpg"]),
            "smoke": float(data["smoke"]),
            "light_intensity": float(data["light_intensity"]),
            "motion_status": int(data["motion_status"])
        }], columns=FEATURE_COLUMNS)

        model = joblib.load(MODEL_PATH)
        scaler = joblib.load(SCALER_PATH)
        label_encoder = joblib.load(LABEL_ENCODER_PATH)

        input_scaled = scaler.transform(input_data)

        prediction_code = model.predict(input_scaled)[0]
        prediction_proba = model.predict_proba(input_scaled)[0]

        prediction_label = label_encoder.inverse_transform([prediction_code])[0]
        prediction_score = float(max(prediction_proba))

        result = {
            "status": "success",
            "prediction_label": prediction_label,
            "prediction_score": round(prediction_score, 4),
            "prediction_code": int(prediction_code)
        }

        print(json.dumps(result))

    except Exception as e:
        result = {
            "status": "error",
            "message": str(e)
        }

        print(json.dumps(result))


if __name__ == "__main__":
    main()

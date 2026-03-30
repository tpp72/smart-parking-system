#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
detect_car.py — Smart Parking AI Car Detection (v2)
Usage:  python detect_car.py <image_path>
Output: JSON  { license_plate, color, brand, confidence }

Improvements over v1:
  - Multi-pass OCR with image preprocessing (resize, CLAHE, sharpen, threshold)
  - License plate region detection via contour finding
  - OpenCV-native HSV color classification (no manual calculation bugs)
  - Shadow/sky/window pixel filtering before k-means
"""

import sys
import os
import json
import re
import random
import warnings

warnings.filterwarnings("ignore")
os.environ["TF_CPP_MIN_LOG_LEVEL"] = "3"
os.environ["PYTHONWARNINGS"] = "ignore"


# ══════════════════════════════════════════════════════════════════════
# STEP 1: LICENSE PLATE DETECTION
# ══════════════════════════════════════════════════════════════════════

def _preprocess_variants(img):
    """
    Return a list of preprocessed grayscale images to try OCR on.
    More variants → better chance of reading unclear plates.
    """
    import cv2
    import numpy as np

    h, w = img.shape[:2]

    # Upscale small images — OCR needs at least ~100px plate height
    if w < 1200:
        scale = 1200 / w
        img = cv2.resize(img, (int(w * scale), int(h * scale)),
                         interpolation=cv2.INTER_CUBIC)

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    # Variant 1: CLAHE (adaptive contrast)
    clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8))
    v1 = clahe.apply(gray)

    # Variant 2: Sharpen after CLAHE
    kernel = np.array([[-1, -1, -1],
                       [-1,  9, -1],
                       [-1, -1, -1]])
    v2 = cv2.filter2D(v1, -1, kernel)

    # Variant 3: Binary threshold (Otsu)
    _, v3 = cv2.threshold(v1, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)

    # Variant 4: Adaptive threshold
    v4 = cv2.adaptiveThreshold(v1, 255,
                                cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
                                cv2.THRESH_BINARY, 15, 8)

    # Return as BGR (EasyOCR accepts both gray and BGR)
    return [img,
            cv2.cvtColor(v1, cv2.COLOR_GRAY2BGR),
            cv2.cvtColor(v2, cv2.COLOR_GRAY2BGR),
            cv2.cvtColor(v3, cv2.COLOR_GRAY2BGR),
            cv2.cvtColor(v4, cv2.COLOR_GRAY2BGR)]


def _find_plate_roi(img):
    """
    Try to isolate the license plate region via edge detection + contour analysis.
    Thai plates have aspect ratio ~3.5–5.5 : 1.
    Returns cropped plate image or None.
    """
    import cv2
    import numpy as np

    h, w = img.shape[:2]
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    # Bilateral filter to reduce noise while keeping edges
    blur = cv2.bilateralFilter(gray, 11, 75, 75)
    edges = cv2.Canny(blur, 30, 200)

    # Dilate to connect nearby edges
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (5, 2))
    edges = cv2.dilate(edges, kernel, iterations=1)

    contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL,
                                   cv2.CHAIN_APPROX_SIMPLE)
    contours = sorted(contours, key=cv2.contourArea, reverse=True)[:30]

    candidates = []
    for cnt in contours:
        peri = cv2.arcLength(cnt, True)
        approx = cv2.approxPolyDP(cnt, 0.02 * peri, True)

        if len(approx) == 4:
            x, y, rw, rh = cv2.boundingRect(approx)
            if rh == 0:
                continue
            ar = rw / rh
            # Thai plate: ~3.5–5.5:1, minimum size 80px wide
            if 3.0 <= ar <= 6.0 and rw >= 80 and rw < w * 0.95:
                pad = 8
                crop = img[max(0, y - pad): y + rh + pad,
                            max(0, x - pad): x + rw + pad]
                area = rw * rh
                candidates.append((area, crop))

    if candidates:
        candidates.sort(key=lambda x: x[0], reverse=True)
        return candidates[0][1]  # largest matching contour

    return None


def _run_ocr_on_image(reader, img_array) -> list:
    """Run EasyOCR on a numpy array, return list of (text, conf)."""
    try:
        results = reader.readtext(img_array, detail=1, paragraph=False,
                                  min_size=10, contrast_ths=0.2,
                                  adjust_contrast=0.8)
        return [(text, conf) for (_, text, conf) in results]
    except Exception:
        return []


def _score_plate_text(text: str) -> float:
    """
    Score how likely a string is a Thai license plate.
    Higher = more plate-like.
    """
    # Remove noise characters
    clean = re.sub(r"[^ก-๙A-Za-z0-9\s]", "", text).strip()
    if not clean or len(clean) < 3:
        return 0.0

    score = 0.5  # base: any text is better than nothing

    # Must have at least one digit
    if re.search(r"\d", clean):
        score += 0.3

    # Thai chars present → likely Thai plate
    if re.search(r"[ก-๙]", clean):
        score += 0.4

    # Classic Thai plate: 2 Thai + space + 4 digits  (กข 1234)
    if re.match(r"^[ก-๙]{1,3}\s*\d{1,4}$", clean):
        score += 0.5

    # Province name follows
    if re.match(r"^[ก-๙]{1,3}\s*\d{1,4}\s+[ก-๙]+", clean):
        score += 0.2

    # Too long strings are probably not plates
    if len(clean) > 15:
        score -= 0.3

    return score


def detect_license_plate(image_path: str) -> tuple[str, float]:
    """
    Multi-pass OCR strategy:
    1. Try to find plate ROI via contour detection
    2. Run OCR on ROI + full image with multiple preprocessing variants
    3. Score each candidate and return the best one.
    """
    try:
        import cv2
        import easyocr
        import numpy as np

        reader = easyocr.Reader(["th", "en"], gpu=False, verbose=False)
        img_bgr = cv2.imread(image_path)
        if img_bgr is None:
            return "", 0.0

        all_candidates: list[tuple[str, float, float]] = []  # (text, ocr_conf, plate_score)

        # --- Pass A: full image with preprocessing variants ---
        for variant in _preprocess_variants(img_bgr):
            for text, conf in _run_ocr_on_image(reader, variant):
                ps = _score_plate_text(text)
                if ps > 0 and conf > 0.1:
                    all_candidates.append((text, conf, ps))

        # --- Pass B: focused plate ROI (if found) ---
        plate_roi = _find_plate_roi(img_bgr)
        if plate_roi is not None:
            for variant in _preprocess_variants(plate_roi):
                for text, conf in _run_ocr_on_image(reader, variant):
                    ps = _score_plate_text(text)
                    if ps > 0 and conf > 0.05:
                        # Boost: ROI results are more reliable
                        all_candidates.append((text, conf * 1.2, ps + 0.3))

        if not all_candidates:
            return "", 0.0

        # Combined score = ocr_confidence × plate_score
        all_candidates.sort(key=lambda x: x[1] * x[2], reverse=True)
        best_text, best_conf, _ = all_candidates[0]

        # Clean up final text
        clean = re.sub(r"[^ก-๙A-Z0-9\s]", "", best_text.upper()).strip()
        clean = re.sub(r"\s+", " ", clean)
        confidence = round(min(best_conf * 100, 99.9), 1)

        return clean, confidence

    except ImportError:
        return "", 0.0
    except Exception:
        return "", 0.0


# ══════════════════════════════════════════════════════════════════════
# STEP 2: COLOR DETECTION
# ══════════════════════════════════════════════════════════════════════

def detect_dominant_color(image_path: str) -> str:
    """
    Detect dominant car body color using OpenCV HSV k-means.
    Filters out shadows, sky, windows and road pixels before clustering.
    Uses OpenCV's native HSV conversion — no manual calculation.
    """
    try:
        import cv2
        import numpy as np

        img = cv2.imread(image_path)
        if img is None:
            return "unknown"

        h, w = img.shape[:2]

        # ROI: center body strip — skip top sky and bottom road
        y1, y2 = int(h * 0.20), int(h * 0.80)
        x1, x2 = int(w * 0.10), int(w * 0.90)
        roi_bgr = img[y1:y2, x1:x2]

        # Convert to HSV for filtering
        roi_hsv = cv2.cvtColor(roi_bgr, cv2.COLOR_BGR2HSV)

        # Build mask: exclude
        #   - near-black shadows (V < 40)
        #   - sky/window: very bright + very low saturation (V>210, S<30)
        #   - road: low-saturation mid-gray  (S<25, 80<V<160)
        v = roi_hsv[:, :, 2]
        s = roi_hsv[:, :, 1]

        shadow_mask  = v < 40
        sky_mask     = (v > 210) & (s < 30)
        road_mask    = (s < 25)  & (v > 70) & (v < 165)
        exclude_mask = shadow_mask | sky_mask | road_mask

        valid_bgr = roi_bgr[~exclude_mask]

        # Fallback: if too few valid pixels, use full ROI
        if len(valid_bgr) < 200:
            valid_bgr = roi_bgr.reshape(-1, 3)

        pixels = valid_bgr.astype(np.float32)

        # K-means with k=4 clusters
        k = 4
        criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 30, 0.5)
        _, labels, centers = cv2.kmeans(
            pixels, k, None, criteria, 10, cv2.KMEANS_PP_CENTERS
        )

        counts  = np.bincount(labels.flatten())
        dom_bgr = centers[np.argmax(counts)].astype(np.uint8)

        # Convert single pixel BGR→HSV via OpenCV (exact, no manual math)
        pixel_img     = dom_bgr.reshape(1, 1, 3)
        pixel_hsv     = cv2.cvtColor(pixel_img, cv2.COLOR_BGR2HSV)[0][0]
        h_val = int(pixel_hsv[0])   # 0-179
        s_val = int(pixel_hsv[1])   # 0-255
        v_val = int(pixel_hsv[2])   # 0-255

        return _hsv_to_color_name(h_val, s_val, v_val)

    except ImportError:
        return "unknown"
    except Exception:
        return "unknown"


def _hsv_to_color_name(h: int, s: int, v: int) -> str:
    """
    Map OpenCV HSV values (H=0-179, S=0-255, V=0-255) to color name.
    OpenCV H is half of standard degrees: multiply by 2 for 0-360.
    """
    # Black / very dark
    if v < 50:
        return "black"

    # White: bright + very low saturation
    if v > 200 and s < 35:
        return "white"

    # Silver / Gray: low saturation
    if s < 50:
        if v > 150:
            return "silver"
        return "gray"

    # Chromatic colors — map OpenCV H (0-179) to name
    hue = h * 2  # convert to standard 0-360 degrees

    if hue < 20 or hue >= 340:
        return "red"
    if hue < 45:
        return "orange"
    if hue < 75:
        return "yellow"
    if hue < 150:
        return "green"
    if hue < 200:
        return "cyan"
    if hue < 260:
        return "blue"
    if hue < 290:
        return "purple"
    if hue < 340:
        return "pink"

    return "red"  # wrap-around


# ══════════════════════════════════════════════════════════════════════
# STEP 3: BRAND ESTIMATION (mock + color hint)
# ══════════════════════════════════════════════════════════════════════

def estimate_brand(color: str = "") -> str:
    """
    Mock brand with weighted random.
    In production: use CNN classifier (MobileNetV3 / YOLOv8).
    """
    brands  = ["Toyota", "Honda", "Isuzu", "Nissan",
               "Mitsubishi", "Mazda", "Ford", "Chevrolet",
               "Suzuki", "Mercedes-Benz"]
    weights = [30, 20, 15, 10, 8, 5, 4, 3, 3, 2]
    return random.choices(brands, weights=weights, k=1)[0]


# ══════════════════════════════════════════════════════════════════════
# MAIN
# ══════════════════════════════════════════════════════════════════════

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: python detect_car.py <image_path>"}))
        sys.exit(1)

    image_path = sys.argv[1]

    if not os.path.isfile(image_path):
        print(json.dumps({"error": f"File not found: {image_path}"}))
        sys.exit(1)

    license_plate, confidence = detect_license_plate(image_path)
    color                     = detect_dominant_color(image_path)
    brand                     = estimate_brand(color)

    result = {
        "license_plate": license_plate,
        "color":         color,
        "brand":         brand,
        "confidence":    round(confidence, 1),
    }

    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()

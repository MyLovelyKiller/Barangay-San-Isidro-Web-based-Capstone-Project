// ============================================================
// SIGNATURE.JS
// Digital Signature Canvas
// ============================================================

document.addEventListener("DOMContentLoaded", () => {
  const canvas = document.getElementById("signatureCanvas");

  if (!canvas) {
    return;
  }

  const ctx = canvas.getContext("2d");

  if (!ctx) {
    console.error("Unable to access signature canvas.");
    return;
  }

  let isDrawing = false;
  let isSigned = false;

  // ==========================================================
  // CANVAS STYLING
  // ==========================================================

  ctx.strokeStyle = "#000000";
  ctx.lineWidth = 2;
  ctx.lineCap = "round";
  ctx.lineJoin = "round";

  // ==========================================================
  // GET POINTER COORDINATES
  // ==========================================================

  function getCoordinates(e) {
    const rect = canvas.getBoundingClientRect();

    return {
      x: e.clientX - rect.left,
      y: e.clientY - rect.top,
    };
  }

  // ==========================================================
  // START DRAWING
  // ==========================================================

  function startDrawing(e) {
    e.preventDefault();

    isDrawing = true;

    const pos = getCoordinates(e);

    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
  }

  // ==========================================================
  // DRAW
  // ==========================================================

  function draw(e) {
    if (!isDrawing) {
      return;
    }

    e.preventDefault();

    const pos = getCoordinates(e);

    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();

    isSigned = true;
  }

  // ==========================================================
  // STOP DRAWING
  // ==========================================================

  function stopDrawing() {
    if (!isDrawing) {
      return;
    }

    isDrawing = false;
    ctx.closePath();
  }

  // ==========================================================
  // MOUSE EVENTS
  // ==========================================================

  canvas.addEventListener("mousedown", startDrawing);

  canvas.addEventListener("mousemove", draw);

  canvas.addEventListener("mouseup", stopDrawing);

  canvas.addEventListener("mouseleave", stopDrawing);

  // ==========================================================
  // TOUCH EVENTS
  // ==========================================================

  canvas.addEventListener("touchstart", startDrawing, { passive: false });

  canvas.addEventListener("touchmove", draw, { passive: false });

  canvas.addEventListener("touchend", stopDrawing, { passive: false });

  canvas.addEventListener("touchcancel", stopDrawing, { passive: false });

  // ==========================================================
  // CLEAR SIGNATURE
  // ==========================================================

  window.clearSignature = function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const digitalSignature = document.getElementById("digital_signature");

    if (digitalSignature) {
      digitalSignature.value = "";
    }

    isSigned = false;
    isDrawing = false;
  };

  // ==========================================================
  // CHECK WHETHER SIGNATURE EXISTS
  // ==========================================================

  window.hasSignature = function () {
    return isSigned;
  };

  // ==========================================================
  // GET SIGNATURE IMAGE DATA
  // ==========================================================

  window.getSignatureData = function () {
    if (!isSigned) {
      return "";
    }

    return canvas.toDataURL("image/png");
  };
});

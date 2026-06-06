const STORAGE_KEY = "bento_offline_orders_v8";
const MODE_KEY = "bento_offline_mode_v8";

const toggleBtn = document.getElementById("toggleOfflineBtn");
const syncBtn = document.getElementById("syncBtn");
const statusBox = document.getElementById("offlineStatus");
const finalizeForm = document.getElementById("orderFinalizeForm");

function isOfflineMode() {
  return localStorage.getItem(MODE_KEY) === "1";
}

function getQueue() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
  } catch (error) {
    return [];
  }
}

function saveQueue(queue) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
}

function setStatus() {
  if (!statusBox) return;

  const offline = isOfflineMode();
  const queue = getQueue();

  statusBox.className = offline
    ? "alert alert-warning mb-0"
    : "alert alert-info mb-0";
  statusBox.textContent = offline
    ? `Mode: Offline | Antrean order lokal: ${queue.length}`
    : `Mode: Online | Antrean order lokal: ${queue.length}`;

  if (toggleBtn) {
    toggleBtn.textContent = offline ? "Matikan Offline" : "Aktifkan Offline";
  }
}

function collectFinalizeOrder(form) {
  const formData = new FormData(form);
  const items = {};

  for (const [key, value] of formData.entries()) {
    const match = key.match(/^items\[(\d+)\]$/);
    if (match && parseInt(value, 10) > 0) {
      items[match[1]] = parseInt(value, 10);
    }
  }

  return {
    open_order_id: parseInt(formData.get("open_order_id") || "0", 10),
    nomor_meja: formData.get("nomor_meja") || "",
    order_type: formData.get("order_type") || "dine_in",
    customer_name: formData.get("customer_name") || "",
    metode_pembayaran: formData.get("metode_pembayaran") || "tunai",
    nominal_diterima: formData.get("nominal_diterima") || 0,
    status: formData.get("status") || "paid",
    items,
    created_at: new Date().toISOString(),
  };
}

function validateOrder(order) {
  if (!order.customer_name) return "Nama/identitas pelanggan wajib diisi.";

  if (order.order_type === "dine_in") {
    if (!order.nomor_meja) return "Nomor meja wajib dipilih untuk dine in.";
  }

  if (order.order_type === "takeaway") {
    if (order.status === "open")
      return "Open bill tidak tersedia untuk takeaway.";
    if (!Object.keys(order.items).length)
      return "Minimal satu menu harus dipilih.";
  }

  if (
    order.order_type === "dine_in" &&
    !Object.keys(order.items).length &&
    !order.open_order_id
  ) {
    return "Minimal satu menu harus dipilih.";
  }

  return null;
}

if (toggleBtn) {
  toggleBtn.addEventListener("click", () => {
    localStorage.setItem(MODE_KEY, isOfflineMode() ? "0" : "1");
    setStatus();
  });
}

if (finalizeForm) {
  finalizeForm.addEventListener("submit", (event) => {
    if (!isOfflineMode()) return;

    event.preventDefault();

    const order = collectFinalizeOrder(finalizeForm);
    const error = validateOrder(order);

    if (error) {
      alert(error);
      return;
    }

    const queue = getQueue();
    queue.push(order);
    saveQueue(queue);
    setStatus();

    alert(
      "Mode offline aktif. Order disimpan sementara di LocalStorage. Saat sync, sistem akan menggabungkan pesanan ini ke open bill meja yang sama bila masih pending.",
    );
    window.location.href = "kasir.php";
  });
}

if (syncBtn) {
  syncBtn.addEventListener("click", async () => {
    const queue = getQueue();

    if (queue.length === 0) {
      alert("Tidak ada order offline yang perlu disinkronkan.");
      setStatus();
      return;
    }

    try {
      const response = await fetch("../API/sync_offline.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ orders: queue }),
      });

      const text = await response.text();
      let data;

      try {
        data = JSON.parse(text);
      } catch (error) {
        throw new Error(
          "Response sync_offline.php bukan JSON. Pastikan masih login sebagai kasir.",
        );
      }

      if (data.synced_count > 0) {
        const failedIndexes = (data.failed_items || []).map(
          (item) => item.local_index,
        );
        const remaining = queue.filter((_, index) =>
          failedIndexes.includes(index),
        );
        saveQueue(remaining);
      }

      setStatus();

      if (data.success) {
        alert(
          `Sinkronisasi berhasil. ${data.synced_count} order masuk database / tergabung ke open bill.`,
        );
        window.location.reload();
      } else {
        alert(
          `Sebagian order gagal disinkronkan. Berhasil: ${data.synced_count}. Cek data order offline.`,
        );
      }
    } catch (error) {
      alert(error.message);
    }
  });
}

window.BentoOffline = {
  isOfflineMode,
  getQueue,
  saveQueue,
  setStatus,
  storageKey: STORAGE_KEY,
  modeKey: MODE_KEY,
};

setStatus();

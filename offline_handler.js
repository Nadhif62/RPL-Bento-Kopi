const STORAGE_KEY = "bento_offline_orders_v2";
const MODE_KEY = "bento_offline_mode_v2";

const form = document.getElementById("orderForm");
const toggleBtn = document.getElementById("toggleOfflineBtn");
const syncBtn = document.getElementById("syncBtn");
const statusBox = document.getElementById("offlineStatus");

function isOfflineMode() {
  return localStorage.getItem(MODE_KEY) === "1";
}

function getQueue() {
  return JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
}

function saveQueue(queue) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
}

function setStatus() {
  if (!statusBox) return;

  const offline = isOfflineMode();
  const queue = getQueue();

  statusBox.className = offline
    ? "alert alert-warning py-2"
    : "alert alert-info py-2";

  statusBox.textContent = offline
    ? `Mode: Offline | Antrean lokal: ${queue.length}`
    : `Mode: Online | Antrean lokal: ${queue.length}`;
}

function collectOrder() {
  const items = {};

  document.querySelectorAll(".item-qty").forEach((input) => {
    const qty = parseInt(input.value, 10);

    if (qty > 0) {
      items[input.dataset.menuId] = qty;
    }
  });

  return {
    nomor_meja: document.getElementById("nomor_meja").value.trim(),
    order_type: document.querySelector('[name="order_type"]').value,
    customer_name: document
      .querySelector('[name="customer_name"]')
      .value.trim(),
    metode_pembayaran: document.getElementById("metode_pembayaran").value,
    nominal_diterima: document.getElementById("nominal_diterima").value,
    status: document.querySelector('[name="status"]').value,
    items: items,
    created_at: new Date().toISOString(),
  };
}

function hasItems(items) {
  return Object.keys(items).length > 0;
}

if (toggleBtn) {
  toggleBtn.addEventListener("click", async () => {
    const nextMode = isOfflineMode() ? "0" : "1";

    localStorage.setItem(MODE_KEY, nextMode);
    setStatus();

    if (nextMode === "0") {
      await syncOfflineOrders();
    }
  });
}

if (form) {
  form.addEventListener("submit", (event) => {
    if (!isOfflineMode()) return;

    event.preventDefault();

    const order = collectOrder();

    if (!order.nomor_meja || !hasItems(order.items)) {
      alert("Nomor meja/nama dan minimal satu item wajib diisi.");
      return;
    }

    const queue = getQueue();

    queue.push(order);
    saveQueue(queue);

    form.reset();
    setStatus();

    alert("Order disimpan ke LocalStorage karena mode offline aktif.");
  });
}

if (syncBtn) {
  syncBtn.addEventListener("click", syncOfflineOrders);
}

async function syncOfflineOrders() {
  const queue = getQueue();

  if (queue.length === 0) {
    setStatus();
    alert("Tidak ada antrean offline.");
    return;
  }

  try {
    const response = await fetch("sync_offline.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        orders: queue,
      }),
    });

    const data = await response.json();

    if (data.success) {
      localStorage.removeItem(STORAGE_KEY);
      alert(`${data.synced_count} order offline berhasil disinkronkan.`);
      location.reload();
    } else {
      const failedIndexes = data.failed_items.map((item) => item.local_index);
      const remaining = queue.filter((_, index) =>
        failedIndexes.includes(index),
      );

      saveQueue(remaining);

      alert(
        `${data.synced_count} order berhasil sync. ${remaining.length} order gagal dan tetap disimpan.`,
      );
      setStatus();
    }
  } catch (error) {
    alert("Sync gagal. Pastikan server aktif.");
  }
}

setStatus();

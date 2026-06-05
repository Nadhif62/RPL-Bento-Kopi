const STORAGE_KEY = "bento_offline_orders_v6";
const MODE_KEY = "bento_offline_mode_v6";
const OPEN_BILL_CACHE_KEY = "bento_open_bills_cache_v6";

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

function getOpenBillCache() {
  return JSON.parse(localStorage.getItem(OPEN_BILL_CACHE_KEY) || "[]");
}

function saveOpenBillCache(data) {
  localStorage.setItem(OPEN_BILL_CACHE_KEY, JSON.stringify(data));
}

function removeOpenBillCache() {
  localStorage.removeItem(OPEN_BILL_CACHE_KEY);
}

function setStatus() {
  if (!statusBox) return;

  const offline = isOfflineMode();
  const queue = getQueue();
  const cache = getOpenBillCache();

  statusBox.className = offline
    ? "alert alert-warning py-2"
    : "alert alert-info py-2";

  statusBox.textContent = offline
    ? `Mode: Offline | Antrean lokal: ${queue.length} | Cache open bill: ${cache.length}`
    : `Mode: Online | Antrean lokal: ${queue.length}`;
}

async function cacheAllOpenBills() {
  const response = await fetch(
    "../API/get_all_open_bills.php?time=" + Date.now(),
    {
      cache: "no-store",
    },
  );

  const text = await response.text();

  let data;

  try {
    data = JSON.parse(text);
  } catch (error) {
    throw new Error(
      "Response API/get_all_open_bills.php bukan JSON. Cek apakah masih login sebagai kasir.",
    );
  }

  if (!data.success) {
    throw new Error(data.message || "Gagal mengambil open bill.");
  }

  saveOpenBillCache(data.open_bills || []);
  setStatus();

  return data.open_bills || [];
}

function hasItems(items) {
  return Object.keys(items).length > 0;
}

function collectOrder() {
  const items = {};
  const itemDetails = [];
  let totalTambahan = 0;

  document.querySelectorAll(".item-qty").forEach((input) => {
    const qty = parseInt(input.value, 10);
    const menuId = input.dataset.menuId;
    const menuName = input.dataset.menuName || "Menu";
    const menuPrice = parseFloat(input.dataset.menuPrice || "0");

    if (qty > 0) {
      items[menuId] = qty;

      const subtotal = qty * menuPrice;
      totalTambahan += subtotal;

      itemDetails.push({
        menu_id: parseInt(menuId, 10),
        nama_menu: menuName,
        jumlah: qty,
        harga_satuan: menuPrice,
        subtotal: subtotal,
      });
    }
  });

  return {
    nomor_meja: document.getElementById("nomor_meja").value.trim(),
    order_type: document.querySelector('[name="order_type"]').value,
    customer_name: document
      .querySelector('[name="customer_name"]')
      .value.trim(),
    metode_pembayaran: document.getElementById("metode_pembayaran").value,
    nominal_diterima: document.getElementById("nominal_diterima").value || 0,
    status: document.querySelector('[name="status"]').value,
    items: items,
    item_details: itemDetails,
    total_tambahan: totalTambahan,
    created_at: new Date().toISOString(),
  };
}

function validateOfflineOrder(order) {
  if (!order.nomor_meja) {
    return "Meja atau nama pelanggan wajib diisi.";
  }

  if (!order.order_type) {
    return "Tipe order wajib diisi.";
  }

  if (!order.metode_pembayaran) {
    return "Metode pembayaran wajib diisi.";
  }

  if (!order.status) {
    return "Status order wajib diisi.";
  }

  if (!hasItems(order.items)) {
    return "Belum ada menu yang dipesan. Isi jumlah minimal 1 pada salah satu menu.";
  }

  return null;
}

function updateCachedOpenBill(order) {
  if (order.status !== "open") return;

  const cache = getOpenBillCache();

  let existing = cache.find((item) => {
    return (
      item.order.nomor_meja === order.nomor_meja &&
      item.order.order_type === order.order_type &&
      item.order.status === "open"
    );
  });

  if (!existing) {
    existing = {
      order: {
        id: "OFFLINE-" + Date.now(),
        nomor_meja: order.nomor_meja,
        order_type: order.order_type,
        customer_name: order.customer_name,
        total_bayar: 0,
        metode_pembayaran: order.metode_pembayaran,
        status: "open",
        tanggal: order.created_at,
      },
      details: [],
    };

    cache.push(existing);
  }

  existing.order.total_bayar += order.total_tambahan;

  if (order.customer_name !== "") {
    existing.order.customer_name = order.customer_name;
  }

  existing.order.metode_pembayaran = order.metode_pembayaran;

  order.item_details.forEach((newItem) => {
    const oldItem = existing.details.find((detail) => {
      return parseInt(detail.menu_id, 10) === parseInt(newItem.menu_id, 10);
    });

    if (oldItem) {
      oldItem.jumlah += newItem.jumlah;
      oldItem.subtotal += newItem.subtotal;
    } else {
      existing.details.push(newItem);
    }
  });

  saveOpenBillCache(cache);
  setStatus();
}

if (toggleBtn) {
  toggleBtn.addEventListener("click", async () => {
    if (!isOfflineMode()) {
      try {
        const cachedBills = await cacheAllOpenBills();

        localStorage.setItem(MODE_KEY, "1");
        setStatus();

        alert(
          `Mode offline aktif. ${cachedBills.length} open bill berhasil dicache.`,
        );
      } catch (error) {
        alert(error.message);
      }

      return;
    }

    localStorage.setItem(MODE_KEY, "0");
    setStatus();

    await syncOfflineOrders();
  });
}

if (form) {
  form.addEventListener("submit", (event) => {
    if (!isOfflineMode()) return;

    event.preventDefault();

    const order = collectOrder();
    const validationMessage = validateOfflineOrder(order);

    if (validationMessage) {
      alert(validationMessage);
      return;
    }

    const queue = getQueue();
    queue.push(order);
    saveQueue(queue);

    updateCachedOpenBill(order);

    if (typeof window.loadOpenBillForTable === "function") {
      window.loadOpenBillForTable(order.nomor_meja, true);
    }

    document.querySelectorAll(".item-qty").forEach((input) => {
      input.value = 0;
    });

    setStatus();

    alert("Order offline berhasil disimpan.");
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
    const response = await fetch("../API/sync_offline.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        orders: queue,
      }),
    });

    const text = await response.text();

    let data;

    try {
      data = JSON.parse(text);
    } catch (error) {
      console.error("Response sync bukan JSON:", text);
      alert(
        "Sync gagal. Response dari server bukan JSON. Cek apakah masih login sebagai kasir.",
      );
      return;
    }

    console.log("SYNC RESULT:", data);

    if (data.success) {
      localStorage.removeItem(STORAGE_KEY);
      removeOpenBillCache();

      alert(`${data.synced_count} order offline berhasil disinkronkan.`);
      location.reload();
      return;
    }

    const failedItems = data.failed_items || [];
    const failedIndexes = failedItems.map((item) => item.local_index);
    const remaining = queue.filter((_, index) => failedIndexes.includes(index));

    saveQueue(remaining);

    let errorText = "";

    if (failedItems.length > 0) {
      errorText = "\n\nContoh error: " + failedItems[0].message;
    }

    alert(
      `${data.synced_count} order berhasil sync. ${remaining.length} order gagal dan tetap disimpan.${errorText}`,
    );
    setStatus();
  } catch (error) {
    alert("Sync gagal. Pastikan server aktif dan login masih valid.");
  }
}

setStatus();

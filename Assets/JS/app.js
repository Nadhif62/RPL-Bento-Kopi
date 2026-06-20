document.addEventListener("DOMContentLoaded", () => {
  initOrderSetupPage();
  initMenuOrderPage();
  initPaymentPage();
});

function formatRupiah(value) {
  const number = Number(value || 0);
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  })
    .format(number)
    .replace("IDR", "Rp")
    .trim();
}

function initOrderSetupPage() {
  const setupForm = document.getElementById("orderSetupForm");
  if (!setupForm) return;

  const orderType = setupForm.dataset.orderType || "dine_in";
  const selectedTableInput = document.getElementById("selectedTableInput");
  const openOrderIdInput = document.getElementById("openOrderIdInput");
  const selectedTableText = document.getElementById("selectedTableText");
  const customerNameInput = document.getElementById("setupCustomerNameInput");
  const openBills = window.BENTO_OPEN_BILLS || {};
  const localBills = buildSetupLocalBills();

  markSetupOfflineTables(localBills);

  document.querySelectorAll(".table-card").forEach((button) => {
    button.addEventListener("click", () => {
      selectSetupTable(button.dataset.tableNumber || "");
    });
  });

  setupForm.addEventListener("submit", (event) => {
    if (!customerNameInput?.value.trim()) {
      event.preventDefault();
      alert("Identitas pelanggan wajib diisi.");
      return;
    }

    if (orderType === "dine_in" && !selectedTableInput?.value) {
      event.preventDefault();
      alert("Pilih meja terlebih dahulu.");
    }
  });

  const initialTable =
    setupForm.dataset.initialTable || selectedTableInput?.value || "";
  if (orderType === "dine_in" && initialTable) {
    selectSetupTable(initialTable);
  }

  function buildSetupLocalBills() {
    const result = {};
    const queue = window.BentoOffline?.getQueue?.() || [];

    queue.forEach((order) => {
      if (order.order_type !== "dine_in" || order.status !== "open") return;
      const table = order.nomor_meja || "";
      if (!table) return;

      if (!result[table]) {
        result[table] = { queue_count: 0 };
      }
      result[table].queue_count += 1;
    });

    return result;
  }

  function markSetupOfflineTables(bills) {
    Object.entries(bills).forEach(([table, bill]) => {
      const card = document.querySelector(
        `.table-card[data-table-number="${cssEscapeValue(table)}"]`,
      );
      if (!card) return;

      if (!card.classList.contains("busy")) {
        card.classList.remove("free");
        card.classList.add("busy", "offline-pending");
        card.dataset.openOrderId = "0";
        const status = card.querySelector(".table-status");
        const total = card.querySelector(".table-total");
        if (status) status.textContent = "Pending Offline";
        if (total) total.textContent = `${bill.queue_count} antrean`;
      } else {
        card.classList.add("offline-pending");
      }
    });
  }

  function selectSetupTable(tableNumber) {
    if (orderType !== "dine_in" || !tableNumber) return;

    const dbBill = openBills[tableNumber] || null;
    const card = document.querySelector(
      `.table-card[data-table-number="${cssEscapeValue(tableNumber)}"]`,
    );
    const openOrderId = dbBill?.id || card?.dataset.openOrderId || 0;

    if (selectedTableInput) selectedTableInput.value = tableNumber;
    if (openOrderIdInput) openOrderIdInput.value = String(openOrderId || 0);

    document.querySelectorAll(".table-card").forEach((item) => {
      item.classList.toggle(
        "selected",
        item.dataset.tableNumber === tableNumber,
      );
    });

    if (selectedTableText) {
      selectedTableText.textContent = tableNumber;
      selectedTableText.className =
        dbBill || localBills[tableNumber]
          ? "selected-table-box busy"
          : "selected-table-box free";
    }

    if (dbBill?.customer_name && customerNameInput) {
      customerNameInput.value = dbBill.customer_name;
    }
  }
}

function cssEscapeValue(value) {
  if (window.CSS?.escape) return window.CSS.escape(value);
  return String(value).replace(/"/g, '\\"');
}

function initMenuOrderPage() {
  const orderForm = document.getElementById("menuOrderForm");
  if (!orderForm) return;

  const orderType = orderForm.dataset.orderType || "dine_in";
  const summaryList = document.getElementById("orderSummaryList");
  const summaryTotal = document.getElementById("orderSummaryTotal");
  const summaryExistingTotal = document.getElementById("summaryExistingTotal");
  const summaryAdditionalTotal = document.getElementById(
    "summaryAdditionalTotal",
  );
  const submitBtn = document.getElementById("goPaymentBtn");
  const emptyText = document.getElementById("emptyOrderText");
  const hiddenContainer = document.getElementById("hiddenItemsContainer");
  const categoryButtons = document.querySelectorAll("[data-category-filter]");
  const menuCards = document.querySelectorAll("[data-category]");
  const selectedTableInput = document.getElementById("selectedTableInput");
  const openOrderIdInput = document.getElementById("openOrderIdInput");
  const localOfflineTotalInput = document.getElementById(
    "localOfflineTotalInput",
  );
  const selectedTableText = document.getElementById("selectedTableText");
  const customerNameInput = document.getElementById("customerNameInput");
  const currentBillBox = document.getElementById("currentBillBox");
  const currentBillTitle = document.getElementById("currentBillTitle");
  const currentBillCustomer = document.getElementById("currentBillCustomer");
  const currentBillNotice = document.getElementById("currentBillNotice");
  const existingBillTotal = document.getElementById("existingBillTotal");
  const existingBillDetails = document.getElementById("existingBillDetails");

  let selectedExistingTotal = 0;
  let selectedLocalOfflineTotal = 0;
  let selectedTableNumber = selectedTableInput?.value || "";
  let currentSelectedItems = [];

  const openBills = window.BENTO_OPEN_BILLS || {};
  const menuMeta = buildMenuMeta();
  const localOpenBills = buildLocalOpenBills(menuMeta);

  applyOfflineTableStatus(localOpenBills);

  categoryButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      const filter = btn.dataset.categoryFilter;

      categoryButtons.forEach((item) => item.classList.remove("active"));
      btn.classList.add("active");

      menuCards.forEach((card) => {
        card.style.display =
          filter === "all" || card.dataset.category === filter
            ? "block"
            : "none";
      });
    });
  });

  document.querySelectorAll("[data-qty-action]").forEach((button) => {
    button.addEventListener("click", () => {
      const target = document.getElementById(button.dataset.target);
      if (!target) return;

      const action = button.dataset.qtyAction;
      const current = parseInt(target.value || "0", 10);
      const next = action === "plus" ? current + 1 : Math.max(0, current - 1);

      target.value = String(next);
      updateOrderSummary();
    });
  });

  document.querySelectorAll(".item-qty").forEach((input) => {
    input.addEventListener("input", updateOrderSummary);
  });

  document.querySelectorAll(".table-card").forEach((button) => {
    button.addEventListener("click", () => {
      selectTable(button.dataset.tableNumber || "");
    });
  });

  orderForm.addEventListener("submit", (event) => {
    updateOrderSummary();

    if (orderType === "dine_in" && !selectedTableInput?.value) {
      event.preventDefault();
      alert("Pilih nomor meja terlebih dahulu.");
      return;
    }

    if (!customerNameInput?.value.trim()) {
      event.preventDefault();
      alert("Nama/identitas pelanggan wajib diisi.");
      return;
    }

    const hasNewItems = Boolean(
      orderForm.querySelector("input[name^='items[']"),
    );
    const hasExistingOpenBill = Number(openOrderIdInput?.value || 0) > 0;
    if (!hasNewItems && !hasExistingOpenBill) {
      event.preventDefault();
      alert("Pilih minimal satu menu terlebih dahulu.");
    }
  });

  function buildMenuMeta() {
    const meta = {};
    document.querySelectorAll(".item-qty").forEach((input) => {
      meta[input.dataset.menuId] = {
        nama_menu: input.dataset.menuName,
        harga_satuan: Number(input.dataset.menuPrice || 0),
      };
    });
    return meta;
  }

  function buildLocalOpenBills(meta) {
    const result = {};
    const queue = window.BentoOffline?.getQueue?.() || [];

    queue.forEach((order, index) => {
      if (order.order_type !== "dine_in" || order.status !== "open") return;
      const table = order.nomor_meja || "";
      if (!table) return;

      if (!result[table]) {
        result[table] = {
          total_bayar: 0,
          details: [],
          queue_count: 0,
        };
      }

      result[table].queue_count += 1;

      Object.entries(order.items || {}).forEach(([menuId, qtyValue]) => {
        const qty = parseInt(qtyValue, 10);
        if (!qty || qty <= 0) return;
        const menu = meta[menuId] || {
          nama_menu: `Menu #${menuId}`,
          harga_satuan: 0,
        };
        const subtotal = qty * Number(menu.harga_satuan || 0);
        result[table].total_bayar += subtotal;
        result[table].details.push({
          menu_id: Number(menuId),
          nama_menu: menu.nama_menu,
          jumlah: qty,
          harga_satuan: Number(menu.harga_satuan || 0),
          subtotal,
          source: "offline",
          queue_index: index,
        });
      });
    });

    return result;
  }

  function applyOfflineTableStatus(localBills) {
    Object.entries(localBills).forEach(([table, bill]) => {
      const card = document.querySelector(
        `.table-card[data-table-number="${cssEscape(table)}"]`,
      );
      if (!card) return;

      if (!card.classList.contains("busy")) {
        card.classList.remove("free");
        card.classList.add("busy", "offline-pending");
        card.dataset.openOrderId = "0";
        const status = card.querySelector(".table-status");
        const customer = card.querySelector(".table-customer");
        const total = card.querySelector(".table-total");
        if (status) status.textContent = "Pending Offline";
        if (customer)
          customer.textContent = `${bill.queue_count} antrean lokal`;
        if (total) total.textContent = formatRupiah(bill.total_bayar);
      } else {
        card.classList.add("offline-pending");
        const total = card.querySelector(".table-total");
        if (total) {
          const dbBill = openBills[table];
          total.textContent = formatRupiah(
            Number(dbBill?.total_bayar || 0) + Number(bill.total_bayar || 0),
          );
        }
      }
    });
  }

  function cssEscape(value) {
    if (window.CSS?.escape) return window.CSS.escape(value);
    return String(value).replace(/"/g, '\\"');
  }

  function selectTable(tableNumber) {
    if (orderType !== "dine_in" || !tableNumber) return;

    selectedTableNumber = tableNumber;
    const dbBill = openBills[tableNumber] || null;
    const localBill = localOpenBills[tableNumber] || null;
    const openOrderId = dbBill?.id || 0;
    selectedExistingTotal = Number(dbBill?.total_bayar || 0);
    selectedLocalOfflineTotal = Number(localBill?.total_bayar || 0);

    if (selectedTableInput) selectedTableInput.value = tableNumber;
    if (openOrderIdInput) openOrderIdInput.value = String(openOrderId);
    if (localOfflineTotalInput) {
      localOfflineTotalInput.value = String(selectedLocalOfflineTotal);
    }

    document.querySelectorAll(".table-card").forEach((card) => {
      card.classList.toggle(
        "selected",
        card.dataset.tableNumber === tableNumber,
      );
    });

    if (selectedTableText) {
      selectedTableText.textContent =
        dbBill || localBill ? `${tableNumber}` : `${tableNumber}`;
      selectedTableText.className =
        dbBill || localBill
          ? "selected-table-box busy"
          : "selected-table-box free";
    }

    if (dbBill?.customer_name && customerNameInput) {
      customerNameInput.value = dbBill.customer_name;
    }

    renderExistingBill(dbBill, localBill, tableNumber);
    updateOrderSummary();
  }

  function renderExistingBill(dbBill, localBill, tableNumber) {
    if (!currentBillBox) return;

    const hasBill = Boolean(dbBill || localBill);
    currentBillBox.style.display = hasBill ? "block" : "none";

    if (!hasBill) {
      if (existingBillDetails) existingBillDetails.innerHTML = "";
      if (existingBillTotal) existingBillTotal.textContent = formatRupiah(0);
      return;
    }

    const combinedTotal =
      Number(dbBill?.total_bayar || 0) + Number(localBill?.total_bayar || 0);

    if (currentBillTitle) {
      currentBillTitle.textContent = dbBill
        ? `Open bill aktif - ${tableNumber}`
        : `Pending offline - ${tableNumber}`;
    }
    if (currentBillCustomer) {
      currentBillCustomer.textContent = dbBill?.customer_name
        ? `Atas nama: ${dbBill.customer_name}`
        : "Pending offline";
    }
    if (existingBillTotal)
      existingBillTotal.textContent = formatRupiah(combinedTotal);
    if (currentBillNotice) {
      currentBillNotice.textContent = localBill
        ? "Sync dulu sebelum lunas."
        : "";
    }

    if (!existingBillDetails) return;
    existingBillDetails.innerHTML = "";

    const details = [
      ...(dbBill?.details || []).map((item) => ({ ...item, source: "online" })),
      ...(localBill?.details || []),
    ];

    details.forEach((item) => {
      const row = document.createElement("div");
      row.className = "summary-row";
      const sourceLabel =
        item.source === "offline" ? " <em>(offline)</em>" : "";
      row.innerHTML = `
        <span>${item.nama_menu}${sourceLabel} <strong>x${item.jumlah}</strong></span>
        <strong>${formatRupiah(item.subtotal)}</strong>
      `;
      existingBillDetails.appendChild(row);
    });
  }

  function updateOrderSummary() {
    const selectedItems = [];
    let additionalTotal = 0;
    hiddenContainer.innerHTML = "";

    document.querySelectorAll(".item-qty").forEach((input) => {
      const qty = parseInt(input.value || "0", 10);
      const menuId = input.dataset.menuId;
      const menuName = input.dataset.menuName;
      const menuPrice = Number(input.dataset.menuPrice || 0);

      if (qty > 0) {
        const subtotal = qty * menuPrice;
        additionalTotal += subtotal;
        selectedItems.push({ menuId, menuName, menuPrice, qty, subtotal });

        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = `items[${menuId}]`;
        hidden.value = qty;
        hiddenContainer.appendChild(hidden);
      }
    });

    currentSelectedItems = selectedItems;
    summaryList.innerHTML = "";

    const hasExistingOpenBill = Number(openOrderIdInput?.value || 0) > 0;
    if (selectedItems.length === 0) {
      emptyText.style.display = "block";
      emptyText.textContent = hasExistingOpenBill
        ? "Tidak ada tambahan."
        : "Belum ada menu dipilih.";
    } else {
      emptyText.style.display = "none";

      selectedItems.forEach((item) => {
        const row = document.createElement("div");
        row.className = "summary-row";
        row.innerHTML = `
          <span>${item.menuName} <strong>x${item.qty}</strong></span>
          <strong>${formatRupiah(item.subtotal)}</strong>
        `;
        summaryList.appendChild(row);
      });
    }

    if (summaryExistingTotal) {
      summaryExistingTotal.textContent = formatRupiah(
        selectedExistingTotal + selectedLocalOfflineTotal,
      );
    }
    if (summaryAdditionalTotal) {
      summaryAdditionalTotal.textContent = formatRupiah(additionalTotal);
    }

    const grandTotal =
      selectedExistingTotal + selectedLocalOfflineTotal + additionalTotal;
    summaryTotal.textContent = formatRupiah(grandTotal);

    if (submitBtn) {
      if (orderType === "dine_in") {
        submitBtn.disabled =
          !selectedTableNumber ||
          (selectedItems.length === 0 && !hasExistingOpenBill);
      } else {
        submitBtn.disabled = selectedItems.length === 0;
      }
    }
  }

  const initialTable = orderForm.dataset.initialTable || selectedTableNumber;
  if (orderType === "dine_in" && initialTable) {
    selectTable(initialTable);
  } else {
    updateOrderSummary();
  }
}

function initPaymentPage() {
  const paymentForm = document.getElementById("orderFinalizeForm");
  if (!paymentForm) return;

  const nominalInput = document.getElementById("nominalDiterima");
  const changeBox = document.getElementById("changePreview");
  const total = Number(paymentForm.dataset.total || 0);
  const cashBox = document.getElementById("cashInputBox");

  const methodInputs = paymentForm.querySelectorAll(
    "input[name='metode_pembayaran']",
  );
  const statusInputs = paymentForm.querySelectorAll("input[name='status']");

  [...methodInputs, ...statusInputs].forEach((input) => {
    input.addEventListener("change", updatePaymentState);
  });

  if (nominalInput) {
    nominalInput.addEventListener("input", updatePaymentState);
  }

  paymentForm.addEventListener("submit", (event) => {
    const method =
      paymentForm.querySelector("input[name='metode_pembayaran']:checked")
        ?.value || "tunai";
    const status =
      paymentForm.querySelector("input[name='status']:checked")?.value ||
      "paid";
    const nominal = Number(nominalInput?.value || 0);
    if (status === "paid" && method === "tunai" && nominal < total) {
      event.preventDefault();
      alert("Nominal tunai tidak boleh kurang dari total pembayaran.");
      return;
    }

    const submitButton = paymentForm.querySelector(
      "button[type='submit'], button:not([type])",
    );
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Memproses...";
    }
  });

  function updatePaymentState() {
    const method =
      paymentForm.querySelector("input[name='metode_pembayaran']:checked")
        ?.value || "tunai";
    const status =
      paymentForm.querySelector("input[name='status']:checked")?.value ||
      "paid";

    const showCash = status === "paid" && method === "tunai";
    if (cashBox) cashBox.style.display = showCash ? "block" : "none";

    if (nominalInput) {
      nominalInput.required = showCash;
      if (!showCash) nominalInput.value = "";
    }

    const nominal = Number(nominalInput?.value || 0);
    const change = Math.max(0, nominal - total);
    if (changeBox) {
      if (status === "open") {
        changeBox.textContent =
          "Pembayaran disimpan sebagai pending/open bill.";
      } else if (method === "qris") {
        changeBox.textContent = "QRIS diproses otomatis sesuai total tagihan.";
      } else {
        changeBox.textContent = `Kembalian: ${formatRupiah(change)}`;
      }
    }
  }

  updatePaymentState();
}

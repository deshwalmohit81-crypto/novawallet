(() => {
  "use strict";

  const base = document.querySelector('meta[name="base-path"]')?.content.replace(/\/$/, "") || "";
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const addressPattern = /^0x[a-fA-F0-9]{40}$/;
  const rememberedAddressKey = "nova-public-address";
  const rememberPreferenceKey = "nova-remember-address";

  const endpoint = (path) => `${base}/api/${path}`;
  const escapeAddress = (value) => addressPattern.test(value);

  async function post(path, payload) {
    const response = await fetch(endpoint(path), {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf, Accept: "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || `Request failed (${response.status}).`);
    return result;
  }

  function showToast(message, type = "success") {
    const region = document.getElementById("toast-region");
    if (!region) return;
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.setAttribute("role", "status");
    toast.textContent = message;
    region.append(toast);
    window.setTimeout(() => toast.remove(), 4800);
  }

  function getAddress(id) {
    const input = document.getElementById(id);
    const address = input?.value.trim() || "";
    if (!escapeAddress(address)) {
      input?.setAttribute("aria-invalid", "true");
      showToast("Enter a public EVM address: 0x followed by 40 hexadecimal characters.", "error");
      input?.focus();
      return "";
    }
    input?.removeAttribute("aria-invalid");
    return address;
  }

  function applyRememberedAddress(inputId) {
    const input = document.getElementById(inputId);
    if (input && localStorage.getItem(rememberPreferenceKey) === "true") {
      input.value = localStorage.getItem(rememberedAddressKey) || "";
    }
  }

  function rememberAddress(address) {
    if (localStorage.getItem(rememberPreferenceKey) === "true") {
      localStorage.setItem(rememberedAddressKey, address);
    }
  }

  function setCardStatus(card, state, message) {
    const status = card.querySelector("[data-status]");
    if (!status) return;
    status.className = `network-status status-${state}`;
    status.replaceChildren(document.createTextNode(message));
    if (state === "error") {
      const retry = document.createElement("button");
      retry.type = "button";
      retry.className = "status-retry";
      retry.textContent = "Retry";
      retry.setAttribute("aria-label", `Retry ${card.querySelector(".balance-name")?.textContent || "network"} balance`);
      retry.addEventListener("click", () => loadNetworkCard(card, card.dataset.address || ""));
      status.append(" · ", retry);
    }
  }

  async function loadNetworkCard(card, address) {
    const balance = card.querySelector("[data-balance]");
    const network = card.dataset.chainId;
    card.dataset.address = address;
    setCardStatus(card, "loading", "Loading");
    balance.textContent = "…";
    try {
      const data = await post("balance.php", { address, chain_id: Number(network) });
      balance.replaceChildren(document.createTextNode(data.native_balance), document.createTextNode(" "));
      const symbol = document.createElement("small");
      symbol.textContent = data.network.symbol;
      balance.append(symbol);
      setCardStatus(card, "ok", "Live");
    } catch (error) {
      balance.textContent = "—";
      setCardStatus(card, "error", "Unavailable");
      card.title = error.message;
    }
  }

  const dashboardForm = document.getElementById("portfolio-form");
  if (dashboardForm && document.getElementById("balance-grid")) {
    const input = document.getElementById("address-input");
    applyRememberedAddress("address-input");
    dashboardForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const address = getAddress("address-input");
      if (!address) return;
      rememberAddress(address);
      const cards = [...document.querySelectorAll(".balance-card")];
      if (!cards.length) {
        showToast("No enabled networks are configured yet.", "error");
        return;
      }
      const stamp = document.getElementById("last-updated");
      stamp.textContent = "Querying configured public RPCs…";
      await Promise.all(cards.map((card) => loadNetworkCard(card, address)));
      stamp.textContent = `Checked ${new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })} · public RPC data`;
    });
    input?.addEventListener("input", () => input.removeAttribute("aria-invalid"));
  }

  if (document.getElementById("token-rows")) {
    applyRememberedAddress("address-input");
    const form = document.getElementById("portfolio-form");
    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const address = getAddress("address-input");
      if (!address) return;
      rememberAddress(address);
      const selected = document.getElementById("network-select")?.value || "";
      let networkData;
      try {
        const networkResponse = await fetch(endpoint("networks.php"), { credentials: "same-origin", headers: { Accept: "application/json" } });
        networkData = await networkResponse.json();
        if (!networkResponse.ok) throw new Error(networkData.error || "Could not load supported networks.");
      } catch (error) {
        showToast(error.message || "Could not load supported networks.", "error");
        return;
      }
      if (!Array.isArray(networkData.networks)) {
        showToast("Supported network data is unavailable.", "error");
        return;
      }
      const networks = selected ? networkData.networks.filter((network) => String(network.chain_id) === selected) : networkData.networks;
      if (!networks.length) {
        showToast("No enabled networks are available.", "error");
        return;
      }
      const rows = document.getElementById("token-rows");
      rows.replaceChildren();
      const stamp = document.getElementById("last-updated");
      stamp.textContent = "Querying public network data…";
      networks.forEach((network) => rows.append(createMessageRow(`${network.name}: querying public RPC…`)));
      const results = await Promise.all(networks.map(async (network) => {
        try {
          return { network, data: await post("balance.php", { address, chain_id: Number(network.chain_id) }) };
        } catch (error) {
          return { network, error: error.message };
        }
      }));
      rows.replaceChildren();
      results.forEach(({ network, data, error }) => {
        if (error) {
          rows.append(createMessageRow(`${network.name}: ${error}`));
          return;
        }
        rows.append(createAssetRow(network, data.native_balance, data.network.symbol, ""));
        data.tokens.forEach((token) => rows.append(createAssetRow(network, token.balance, token.symbol, token.contract_address, token.name)));
      });
      if (!rows.children.length) rows.append(createMessageRow("No token balances are available."));
      stamp.textContent = `Updated ${new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })} · live public data`;
    });
  }

  function createAssetRow(network, amount, symbol, contract, name = "") {
    const row = document.createElement("tr");
    const asset = document.createElement("td");
    const assetWrap = document.createElement("div");
    assetWrap.className = "asset-name";
    const avatar = document.createElement("span");
    avatar.className = `token-avatar token-${network.chain_id}`;
    avatar.textContent = symbol.slice(0, 1);
    const label = document.createElement("span");
    const title = document.createElement("strong");
    title.textContent = name || symbol;
    const subtitle = document.createElement("small");
    subtitle.textContent = `${network.name} · ${symbol}`;
    label.append(title, subtitle);
    assetWrap.append(avatar, label);
    asset.append(assetWrap);
    const contractCell = document.createElement("td");
    if (contract) {
      const code = document.createElement("code");
      code.className = "contract-link";
      code.textContent = `${contract.slice(0, 8)}…${contract.slice(-6)}`;
      code.title = contract;
      contractCell.append(code);
    } else {
      contractCell.textContent = "Native coin";
    }
    const amountCell = document.createElement("td");
    amountCell.className = "align-right";
    amountCell.textContent = `${amount} ${symbol}`;
    row.append(asset, contractCell, amountCell);
    return row;
  }

  function createMessageRow(message) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 3;
    cell.textContent = message;
    cell.className = "table-message";
    row.append(cell);
    return row;
  }

  const activityForm = document.getElementById("activity-form");
  if (activityForm) {
    applyRememberedAddress("activity-address");
    activityForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const address = getAddress("activity-address");
      if (!address) return;
      rememberAddress(address);
      const chainId = Number(document.getElementById("activity-network")?.value);
      const list = document.getElementById("activity-list");
      const updated = document.getElementById("activity-updated");
      list.replaceChildren(createEmptyActivity("Loading verified explorer records…"));
      updated.textContent = "Checking configured explorer API…";
      try {
        const [result, networksResponse] = await Promise.all([
          post("transactions.php", { address, chain_id: chainId }),
          fetch(endpoint("networks.php"), { credentials: "same-origin", headers: { Accept: "application/json" } }),
        ]);
        const networkData = await networksResponse.json();
        const network = networkData.networks.find((item) => Number(item.chain_id) === chainId);
        list.replaceChildren();
        if (!result.configured) {
          list.append(createEmptyActivity(result.message));
        } else if (!result.transactions.length) {
          list.append(createEmptyActivity("The configured explorer returned no transactions for this address."));
        } else {
          result.transactions.forEach((transaction) => list.append(createActivityRow(transaction, network)));
        }
        updated.textContent = `Checked ${new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })} · explorer API`;
      } catch (error) {
        list.replaceChildren(createEmptyActivity(error.message, "Activity unavailable"));
        updated.textContent = "Request failed · retry when ready";
      }
    });
  }

  function createEmptyActivity(message, heading = "No activity to show") {
    const empty = document.createElement("div");
    empty.className = "empty-state-inline";
    const icon = document.createElement("span");
    icon.className = "empty-icon";
    icon.textContent = "↗";
    const title = document.createElement("strong");
    title.textContent = heading;
    const detail = document.createElement("span");
    detail.textContent = message;
    empty.append(icon, title, detail);
    return empty;
  }

  function createActivityRow(transaction, network) {
    const row = document.createElement("article");
    row.className = "activity-row";
    const symbol = document.createElement("span");
    symbol.className = "activity-symbol";
    symbol.textContent = "↗";
    const main = document.createElement("div");
    main.className = "activity-main";
    const title = document.createElement("strong");
    title.textContent = `${transaction.from.slice(0, 7)}…${transaction.from.slice(-5)} → ${transaction.to ? `${transaction.to.slice(0, 7)}…${transaction.to.slice(-5)}` : "Contract creation"}`;
    const meta = document.createElement("small");
    meta.textContent = transaction.timestamp ? new Date(transaction.timestamp).toLocaleString() : network.name;
    const txLink = document.createElement("a");
    txLink.className = "activity-hash";
    txLink.href = `${network.explorer_url}/tx/${encodeURIComponent(transaction.hash)}`;
    txLink.target = "_blank";
    txLink.rel = "noopener noreferrer";
    txLink.textContent = `${transaction.hash.slice(0, 16)}…${transaction.hash.slice(-10)}`;
    main.append(title, meta, txLink);
    const value = document.createElement("span");
    value.className = "activity-value";
    value.textContent = `${transaction.value} ${network.currency_symbol}`;
    const status = document.createElement("span");
    status.className = "activity-state";
    status.textContent = transaction.status;
    row.append(symbol, main, value, status);
    return row;
  }

  const addressForm = document.getElementById("address-view-form");
  if (addressForm) {
    let activeAddress = "";
    const input = document.getElementById("viewer-address");
    const button = document.getElementById("generate-qr");
    addressForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const address = getAddress("viewer-address");
      if (!address) return;
      activeAddress = address;
      button.disabled = false;
      const details = document.getElementById("address-details");
      details.replaceChildren();
      const heading = document.createElement("span");
      heading.className = "eyebrow";
      heading.textContent = "ADDRESS DETAILS";
      const value = document.createElement("div");
      value.className = "address-value-box";
      const code = document.createElement("code");
      code.textContent = address;
      const copy = document.createElement("button");
      copy.type = "button";
      copy.className = "copy-address";
      copy.textContent = "Copy";
      copy.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(address);
          copy.textContent = "Copied";
          window.setTimeout(() => { copy.textContent = "Copy"; }, 1500);
        } catch {
          showToast("Clipboard access is unavailable in this browser.", "error");
        }
      });
      value.append(code, copy);
      const note = document.createElement("p");
      note.className = "form-hint";
      note.textContent = "Address format is valid. This viewer does not connect to a wallet or verify ownership.";
      const links = document.createElement("div");
      links.className = "explorer-links";
      try {
        const response = await fetch(endpoint("networks.php"), { credentials: "same-origin", headers: { Accept: "application/json" } });
        const networkData = await response.json();
        networkData.networks.forEach((network) => {
          const link = document.createElement("a");
          link.className = "explorer-link";
          link.href = `${network.explorer_url}/address/${encodeURIComponent(address)}`;
          link.target = "_blank";
          link.rel = "noopener noreferrer";
          link.textContent = `${network.name} ↗`;
          links.append(link);
        });
      } catch {
        const unavailable = document.createElement("span");
        unavailable.className = "form-hint";
        unavailable.textContent = "Explorer links are unavailable until network setup is complete.";
        links.append(unavailable);
      }
      details.append(heading, value, note, links);
      document.getElementById("qr-display").replaceChildren(document.createTextNode("QR\nPREVIEW"));
    });
    input?.addEventListener("input", () => {
      button.disabled = true;
      activeAddress = "";
    });
    button.addEventListener("click", () => {
      if (!activeAddress) return;
      const container = document.getElementById("qr-display");
      container.replaceChildren();
      const render = () => {
        if (typeof window.QRCode !== "function") {
          container.textContent = "QR library could not be loaded. Retry when online.";
          return;
        }
        new window.QRCode(container, { text: activeAddress, width: 144, height: 144, colorDark: "#11131e", colorLight: "#f7f7fb", correctLevel: window.QRCode.CorrectLevel.M });
      };
      if (typeof window.QRCode === "function") {
        render();
      } else {
        const script = document.createElement("script");
        script.src = "https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js";
        script.integrity = "sha384-3zSEDfvllQohrq0PHL1fOXJuC/jSOO34H46t6UQfobFOmxE5BpjjaIJY5F2/bMnU";
        script.crossOrigin = "anonymous";
        script.onload = render;
        script.onerror = () => { container.textContent = "QR library could not be loaded. Retry when online."; };
        document.head.append(script);
      }
    });
  }

  const rememberCheckbox = document.getElementById("remember-address");
  if (rememberCheckbox) {
    rememberCheckbox.checked = localStorage.getItem(rememberPreferenceKey) === "true";
    const preferred = document.getElementById("preferred-network");
    preferred.value = localStorage.getItem("nova-preferred-network") || "";
    document.getElementById("save-settings")?.addEventListener("click", () => {
      localStorage.setItem(rememberPreferenceKey, String(rememberCheckbox.checked));
      localStorage.setItem("nova-preferred-network", preferred.value);
      if (!rememberCheckbox.checked) localStorage.removeItem(rememberedAddressKey);
      document.getElementById("settings-feedback").textContent = "Preferences saved on this device.";
    });
  }

  async function submitAdmin(action, form, extra = {}) {
    const payload = { action, ...extra };
    if (form) {
      for (const [key, value] of new FormData(form).entries()) payload[key] = value;
      form.querySelectorAll('input[type="checkbox"]').forEach((input) => { payload[input.name] = input.checked; });
    }
    return post("admin.php", payload);
  }

  document.getElementById("admin-login-form")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const feedback = document.getElementById("admin-feedback");
    feedback.textContent = "Signing in…";
    try {
      await submitAdmin("login", null, { password: document.getElementById("admin-password").value });
      window.location.reload();
    } catch (error) {
      feedback.textContent = error.message;
    }
  });

  document.querySelectorAll(".network-settings-form").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const network = form.closest(".admin-network");
      const feedback = form.querySelector(".admin-feedback");
      feedback.textContent = "Saving…";
      try {
        const result = await submitAdmin("network_update", form, { chain_id: Number(network.dataset.chainId) });
        feedback.textContent = result.message;
        showToast(result.message);
      } catch (error) {
        feedback.textContent = error.message;
      }
    });
    form.querySelector(".health-check")?.addEventListener("click", async (event) => {
      const button = event.currentTarget;
      const feedback = form.querySelector(".admin-feedback");
      button.disabled = true;
      feedback.textContent = "Checking RPC…";
      try {
        const result = await submitAdmin("health_check", null, { chain_id: Number(form.closest(".admin-network").dataset.chainId) });
        feedback.textContent = result.message;
        showToast(result.message, result.ok ? "success" : "error");
        window.setTimeout(() => window.location.reload(), 1000);
      } catch (error) {
        feedback.textContent = error.message;
      } finally {
        button.disabled = false;
      }
    });
  });

  document.getElementById("token-add-form")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const feedback = form.querySelector(".admin-feedback");
    feedback.textContent = "Adding token…";
    try {
      const result = await submitAdmin("token_add", form);
      feedback.textContent = result.message;
      showToast(result.message);
      window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
      feedback.textContent = error.message;
    }
  });

  document.querySelectorAll(".token-delete").forEach((button) => {
    button.addEventListener("click", async () => {
      if (!window.confirm("Remove this token from the configured read-only list?")) return;
      try {
        const result = await submitAdmin("token_delete", null, { token_id: Number(button.dataset.tokenId) });
        showToast(result.message);
        button.closest(".configured-token").remove();
      } catch (error) {
        showToast(error.message, "error");
      }
    });
  });

  document.getElementById("admin-logout")?.addEventListener("click", async () => {
    try {
      await submitAdmin("logout", null);
      window.location.reload();
    } catch (error) {
      showToast(error.message, "error");
    }
  });

  document.getElementById("menuToggle")?.addEventListener("click", () => {
    document.getElementById("sidebar")?.classList.toggle("open");
  });
  document.addEventListener("click", (event) => {
    const sidebar = document.getElementById("sidebar");
    if (sidebar?.classList.contains("open") && !sidebar.contains(event.target) && !event.target.closest("#menuToggle")) {
      sidebar.classList.remove("open");
    }
  });
})();

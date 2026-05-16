(function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  function setCartCount(count) {
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
      el.textContent = String(count);
    });
  }

  function showToast(message, ok) {
    const toast = document.createElement('div');
    toast.className = 'toast ' + (ok ? 'toast-ok' : 'toast-error');
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2800);
  }

  async function postForm(form) {
    const button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    const data = new FormData(form);
    if (csrf && !data.has('csrf_token')) data.append('csrf_token', csrf);

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
        body: data,
      });
      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Request failed.');
      if (typeof json.cart_count !== 'undefined') setCartCount(json.cart_count);
      return json;
    } finally {
      if (button) button.disabled = false;
    }
  }

  document.querySelectorAll('form.ajax-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      try {
        const json = await postForm(form);
        showToast(json.message || 'Updated.', true);
        if (form.action.includes('cart-remove') || form.action.includes('cart-clear')) {
          setTimeout(() => window.location.reload(), 350);
        }
      } catch (error) {
        showToast(error.message, false);
      }
    });
  });

  document.querySelectorAll('form.checkout-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[data-checkout-button]');
      const result = document.querySelector('[data-checkout-result]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Processing...';
      }
      try {
        const json = await postForm(form);
        if (result) result.innerHTML = `<div class="alert alert-success">Order #${json.order_id} completed for ${json.formatted_total}.</div>`;
        showToast('Checkout complete.', true);
        setTimeout(() => window.location.href = '/orders.php', 900);
      } catch (error) {
        if (result) result.innerHTML = `<div class="alert alert-error"></div>`;
        if (result) result.querySelector('.alert').textContent = error.message;
        showToast(error.message, false);
        if (button) {
          button.disabled = false;
          button.textContent = 'Checkout';
        }
      }
    });
  });

  async function refreshBalance() {
    const targets = document.querySelectorAll('[data-balance]');
    if (!targets.length) return;
    try {
      const response = await fetch('/api/balance.php', { headers: { 'Accept': 'application/json' } });
      const json = await response.json();
      targets.forEach((el) => {
        el.textContent = json.success ? json.formatted : 'Balance config error';
        el.title = json.success ? '' : (json.error || 'Unable to load balance.');
      });
    } catch (error) {
      targets.forEach((el) => {
        el.textContent = 'Offline';
        el.title = error.message;
      });
    }
  }

  refreshBalance();
  setInterval(refreshBalance, 3000);
})();

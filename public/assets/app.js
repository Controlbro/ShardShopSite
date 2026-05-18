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


  document.querySelectorAll('[data-cart-quantity-form]').forEach((form) => {
    const input = form.querySelector('input[name="quantity"]');
    const row = form.closest('[data-cart-row]');
    let lastValue = input ? input.value : '';
    let updateTimer = null;

    async function submitQuantity() {
      if (!input || input.value === lastValue) return;
      input.value = String(Math.max(0, Math.min(99, Number.parseInt(input.value || '0', 10) || 0)));
      try {
        const json = await postForm(form);
        lastValue = String(json.quantity);
        input.value = lastValue;
        document.querySelectorAll('[data-cart-total]').forEach((el) => {
          el.textContent = json.formatted_total;
        });
        if (row) {
          const lineTotal = row.querySelector('[data-line-total]');
          if (lineTotal) lineTotal.textContent = json.formatted_line_total;
          if (json.removed) {
            row.remove();
            if (!document.querySelector('[data-cart-row]')) {
              window.location.reload();
            }
          }
        }
        showToast(json.message || 'Cart updated.', true);
      } catch (error) {
        input.value = lastValue;
        showToast(error.message, false);
      }
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      submitQuantity();
    });

    if (input) {
      input.addEventListener('change', submitQuantity);
      input.addEventListener('input', () => {
        clearTimeout(updateTimer);
        updateTimer = setTimeout(submitQuantity, 650);
      });
    }
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



  function updateCommandLabels(editor) {
    editor.querySelectorAll('.command-box').forEach((box, index) => {
      const textarea = box.querySelector('textarea');
      const remove = box.querySelector('[data-remove-command]');
      const text = textarea ? textarea.value : '';
      box.childNodes[0].textContent = `Command ${index + 1}`;
      if (textarea) textarea.value = text;
      if (remove) remove.hidden = editor.querySelectorAll('.command-box').length <= 1;
    });
  }

  function createCommandBox(editor) {
    const label = document.createElement('label');
    label.className = 'command-box';
    label.append('Command');

    const textarea = document.createElement('textarea');
    textarea.name = 'commands[]';
    textarea.rows = 2;
    textarea.required = true;
    textarea.placeholder = 'say Thanks {player}!';
    label.appendChild(textarea);

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'command-remove';
    remove.dataset.removeCommand = '';
    remove.setAttribute('aria-label', 'Remove command');
    remove.textContent = '×';
    label.appendChild(remove);

    editor.appendChild(label);
    updateCommandLabels(editor);
    textarea.focus();
  }

  document.querySelectorAll('[data-commands-editor]').forEach((editor) => {
    updateCommandLabels(editor);
    editor.addEventListener('click', (event) => {
      const addButton = event.target.closest('[data-add-command]');
      const removeButton = event.target.closest('[data-remove-command]');
      if (addButton) {
        createCommandBox(editor);
      }
      if (removeButton) {
        const boxes = editor.querySelectorAll('.command-box');
        if (boxes.length > 1) {
          removeButton.closest('.command-box')?.remove();
          updateCommandLabels(editor);
        }
      }
    });
  });

  function refreshImagePreview(form) {
    const input = form.querySelector('[data-image-input]');
    const preview = form.querySelector('[data-image-preview]');
    if (!input || !preview) return;
    const url = input.value.trim();
    preview.textContent = '';
    if (!url) {
      const fallback = document.createElement('span');
      fallback.textContent = '✦';
      preview.appendChild(fallback);
      return;
    }
    const img = document.createElement('img');
    img.src = url;
    img.alt = 'Item image preview';
    img.onerror = () => {
      preview.textContent = '';
      const fallback = document.createElement('span');
      fallback.textContent = '?';
      preview.appendChild(fallback);
    };
    preview.appendChild(img);
  }

  document.querySelectorAll('[data-admin-item-form]').forEach((form) => {
    const input = form.querySelector('[data-image-input]');
    if (input) {
      input.addEventListener('input', () => refreshImagePreview(form));
    }

    form.querySelectorAll('[data-confirm-delete]').forEach((button) => {
      button.addEventListener('click', (event) => {
        const itemName = form.querySelector('input[name="name"]')?.value || 'this item';
        if (!window.confirm(`Delete ${itemName}? This cannot be undone.`)) {
          event.preventDefault();
        }
      });
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

  function showLoginIntro() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('welcome') !== '1') return;

    const username = document.querySelector('.username')?.textContent?.trim() || 'Player';
    const overlay = document.createElement('div');
    overlay.className = 'login-intro';

    const gem = document.createElement('div');
    gem.className = 'intro-gem';
    gem.textContent = '✦';

    const eyebrow = document.createElement('p');
    eyebrow.className = 'eyebrow';
    eyebrow.textContent = 'Access Granted';

    const heading = document.createElement('h2');
    heading.textContent = `Welcome, ${username}`;

    const message = document.createElement('p');
    message.textContent = 'Syncing your Shards balance and opening the reward vault...';

    const bar = document.createElement('div');
    bar.className = 'intro-bar';
    bar.appendChild(document.createElement('span'));

    overlay.append(gem, eyebrow, heading, message, bar);
    document.body.appendChild(overlay);

    params.delete('welcome');
    const cleanUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}${window.location.hash}`;
    window.history.replaceState({}, '', cleanUrl);
    setTimeout(() => overlay.classList.add('login-intro-out'), 2550);
    setTimeout(() => overlay.remove(), 3200);
  }

  showLoginIntro();

})();

// Mirrors app/Services/Cekbot/CekbotFlowService so the builder preview shows the
// exact copy the bot sends. Keep the two in sync when either changes.

const NUMBER_EMOJI = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟'];

export function money(currency, amount) {
  const value = Number(amount || 0);
  let formatted = value.toFixed(2);
  if (formatted.endsWith('.00')) formatted = formatted.slice(0, -3);
  return `${currency || 'RM'}${formatted}`;
}

function packageMenu(flow) {
  const lines = (flow.packages || []).map((p, i) => {
    const bullet = NUMBER_EMOJI[i] ?? `${i + 1}.`;
    return `${bullet} ${p.label || 'Pakej'} – ${money(p.currency, p.price)}`;
  });
  const prompt = (flow.package_prompt || '').trim() || 'Balas nombor pakej yang berminat 🙂';
  return `${lines.join('\n')}\n\n${prompt}`;
}

function confirmation(flow, vars) {
  let template = (flow.confirmation_message || '').trim();
  if (!template) {
    template =
      'Terima kasih {name}! 🎉\n\n' +
      'Pesanan anda telah kami terima:\n' +
      '🧾 No. Pesanan: {order_number}\n' +
      '📦 Pakej: {package}\n' +
      '💰 Jumlah: {price}\n\n' +
      'Pasukan kami akan proses & hubungi anda tak lama lagi. 🙏';
  }
  return template
    .replaceAll('{order_number}', vars.order_number)
    .replaceAll('{package}', vars.package)
    .replaceAll('{price}', vars.price)
    .replaceAll('{name}', vars.name);
}

/**
 * Build the scripted conversation for a payment path ('cod' | 'transfer').
 * Returns [{ from: 'bot'|'cust', text }].
 */
export function buildPreview(flow, path = 'cod') {
  const msgs = [];
  const bot = (text) => msgs.push({ from: 'bot', text });
  const cust = (text) => msgs.push({ from: 'cust', text });

  const pkg = (flow.packages || [])[1] || (flow.packages || [])[0] || { label: 'Pakej', price: 0, currency: 'RM' };
  const pickIndex = (flow.packages || []).indexOf(pkg);

  // 1. Trigger + package menu.
  const triggerWord = (flow.trigger_keywords || [])[0] || 'nak order';
  cust(triggerWord);
  const intro = (flow.welcome_message || '').trim();
  bot(intro ? `${intro}\n\n${packageMenu(flow)}` : packageMenu(flow));

  if (!(flow.packages || []).length) {
    return msgs;
  }

  // 2. Pick a package.
  cust(String(pickIndex >= 0 ? pickIndex + 1 : 1));

  const both = flow.ask_payment && flow.payment_transfer_enabled && flow.payment_cod_enabled;
  let method = null;
  if (flow.ask_payment) {
    if (both) method = path;
    else if (flow.payment_transfer_enabled) method = 'transfer';
    else if (flow.payment_cod_enabled) method = 'cod';
  }

  if (both) {
    bot(
      `Bagus! Anda pilih *${pkg.label}* (${money(pkg.currency, pkg.price)}).\n\n` +
        'Nak buat pembayaran macam mana?\n1️⃣ Transfer / Online Banking\n2️⃣ COD (Bayar semasa terima)\n\nBalas *1* atau *2* ye 🙂'
    );
    cust(method === 'transfer' ? '1' : '2');
  }

  // 3. Name.
  if (flow.ask_name) {
    const prefix = !both && method
      ? `Bagus! Anda pilih *${pkg.label}* (${money(pkg.currency, pkg.price)}).\n\n`
      : '';
    bot(`${prefix}Boleh saya dapatkan *nama penuh* anda? 🙂`);
    cust('Ahmad Bin Ali');
  }

  // 4. Payment branch.
  if (method === 'cod') {
    bot('Baik! Untuk COD, boleh kongsi *alamat penuh* penghantaran? (nama penerima, alamat, poskod, bandar) 🏠');
    cust('No 5, Jalan Mawar, 43000 Kajang, Selangor');
  } else if (method === 'transfer') {
    const bank = (flow.bank_details || '').trim();
    const extra = (flow.transfer_instructions || '').trim();
    const lines = ['Baik! Untuk bayaran transfer, sila bank-in:'];
    if (bank) { lines.push('', bank); }
    lines.push('', `Jumlah: *${money(pkg.currency, pkg.price)}*`);
    if (extra) { lines.push('', extra); }
    lines.push('', 'Hantar *resit/screenshot* bila dah transfer ye 🙏');
    bot(lines.join('\n'));
    cust('dah transfer ✅');
  }

  // 5. Confirmation.
  bot(
    confirmation(flow, {
      order_number: 'PO-20260914-AB12CD',
      package: pkg.label,
      price: money(pkg.currency, pkg.price),
      name: 'Ahmad Bin Ali',
    })
  );

  return msgs;
}

// Exact decimal arithmetic. Pending/outbox records are intentionally excluded.
const titles = { income_statement: 'قائمة الدخل (الأرباح والخسائر)', cash_flow: 'ملخص التدفق النقدي', sales: 'تقرير المبيعات', expenses: 'تقرير المصاريف', receivables: 'تقرير المبالغ المستحقة لك (الذمم المدينة)', payables: 'تقرير المبالغ المستحقة عليك (الذمم الدائنة)', monthly: 'الأداء المالي الشهري' };
const minor = value => {
    const s = String(value); if (!/^\d+(?:\.\d{1,2})?$/.test(s)) throw new Error('بيانات مالية غير صالحة.');
    const [whole, fraction = ''] = s.split('.'); return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
};
const money = value => (value < 0n ? '-' : '') + (value < 0n ? -value : value) / 100n + '.' + ((value < 0n ? -value : value) % 100n).toString().padStart(2, '0');
const inflow = tx => ['sale', 'income'].includes(tx.type);
const between = (day, from, to) => day >= from && day <= to;
const section = (title, columns, rows) => ({ title, columns, rows });
const sorted = map => [...map.entries()].sort(([a], [b]) => a < b ? -1 : a > b ? 1 : 0);
export function localReport(snapshot, filters) {
    const { report: key, from, to, basis = 'accrual' } = filters;
    if (!snapshot.complete) throw new Error('النسخة المالية غير مكتملة. حدّثها عبر الاتصال قبل احتساب التقارير.');
    const validDate = date => { const d = new Date(date + 'T00:00:00Z'); return Number.isFinite(d.getTime()) && d.toISOString().slice(0, 10) === date; };
    if (!validDate(from) || !validDate(to) || !titles[key] || !['accrual', 'cash'].includes(basis) || !/^\d{4}-\d{2}-\d{2}$/.test(from) || !/^\d{4}-\d{2}-\d{2}$/.test(to) || from > to) throw new Error('اختر تقريراً وفترة صحيحة.');
    const txs = snapshot.transactions.filter(t => t.status === 'posted');
    const period = txs.filter(t => between(t.date, from, to));
    const paid = (t, asOf = null) => t.settlements.filter(s => !asOf || s.date <= asOf).reduce((n, s) => n + minor(s.amount), 0n);
    const payments = txs.flatMap(t => t.settlements.filter(s => between(s.date, from, to)).map(s => ({ ...s, transaction: t })));
    let sections;
    if (key === 'income_statement' || key === 'cash_flow') {
        const totals = new Map();
        const values = key === 'cash_flow' || basis === 'cash' ? payments.map(s => ({ tx: s.transaction, amount: minor(s.amount) })) : period.map(tx => ({ tx, amount: minor(tx.amount) }));
        for (const { tx, amount } of values) { const value = totals.get(tx.currency) ?? { in: 0n, out: 0n }; value[inflow(tx) ? 'in' : 'out'] += amount; totals.set(tx.currency, value); }
        sections = [section(key === 'cash_flow' ? 'حركة النقد' : 'الملخص', key === 'cash_flow' ? ['العملة', 'النقد الداخل', 'النقد الخارج', 'صافي الحركة'] : ['العملة', 'الإيرادات', 'المصاريف والمشتريات', 'صافي الربح أو الخسارة'], sorted(totals).map(([c, v]) => [c, money(v.in), money(v.out), money(v.in - v.out)]))];
    } else if (key === 'sales' || key === 'expenses') {
        const groups = new Map();
        for (const t of period.filter(tx => inflow(tx) === (key === 'sales'))) {
            const label = snapshot.categories[t.type]?.[t.category] ?? t.category;
            const type = snapshot.types[t.type] ?? t.type;
            const code = JSON.stringify([t.currency, type, label]);
            const v = groups.get(code) ?? { currency: t.currency, type, label, count: 0, amount: 0n, paid: 0n };
            v.count++; v.amount += minor(t.amount); v.paid += paid(t); groups.set(code, v);
        }
        sections = [section(key === 'sales' ? 'المبيعات والدخل' : 'المشتريات والمصاريف', ['العملة', 'النوع', 'التصنيف', 'عدد العمليات', 'المبلغ', 'المدفوع', 'المتبقي'], sorted(groups).map(([, v]) => [v.currency, v.type, v.label, String(v.count), money(v.amount), money(v.paid), money(v.amount - v.paid)]))];
    } else if (key === 'receivables' || key === 'payables') {
        const totals = new Map(), rows = [];
        for (const t of txs.filter(t => t.date <= to && inflow(t) === (key === 'receivables')).sort((a, b) => a.date.localeCompare(b.date))) {
            const left = minor(t.amount) - paid(t, to); if (left <= 0n) continue;
            totals.set(t.currency, (totals.get(t.currency) ?? 0n) + left);
            rows.push([t.date, t.counterparty ?? '—', snapshot.types[t.type] ?? t.type, t.currency, money(minor(t.amount)), money(left)]);
        }
        sections = [section('المجموع حسب العملة', ['العملة', 'المبلغ المتبقي'], sorted(totals).map(([c, v]) => [c, money(v)])), section('التفاصيل', ['التاريخ', 'الجهة', 'النوع', 'العملة', 'المبلغ الأصلي', 'المتبقي'], rows)];
    } else {
        const groups = new Map();
        const group = (day, currency) => { const k = day.slice(0, 7) + '|' + currency; if (!groups.has(k)) groups.set(k, { in: 0n, out: 0n, cash_in: 0n, cash_out: 0n }); return groups.get(k); };
        for (const t of period) group(t.date, t.currency)[inflow(t) ? 'in' : 'out'] += minor(t.amount);
        for (const s of payments) group(s.date, s.transaction.currency)[inflow(s.transaction) ? 'cash_in' : 'cash_out'] += minor(s.amount);
        sections = [section('حسب الشهر', ['الشهر', 'العملة', 'الإيرادات', 'المصاريف والمشتريات', 'الصافي', 'النقد الداخل', 'النقد الخارج'], sorted(groups).map(([k, v]) => [...k.split('|'), money(v.in), money(v.out), money(v.in - v.out), money(v.cash_in), money(v.cash_out)]))];
    }
    return { key, title: titles[key], period_label: from + ' — ' + to, sections, notes: ['تقرير محلي من آخر نسخة محفوظة بتاريخ ' + snapshot.saved_at + '. لا يتضمن الطلبات غير المرسلة أو تغييرات الخادم اللاحقة. المبالغ لكل عملة على حدة دون تحويل.'] };
}

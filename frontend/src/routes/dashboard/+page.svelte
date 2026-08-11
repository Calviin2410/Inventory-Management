<script>
	import { onMount } from 'svelte';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';
	let recentInvoices = $state([]), loading = $state(true), errorMessage = $state('');
	onMount(async () => { try { recentInvoices = await api.getInvoices({ limit: 5 }); } catch (error) { errorMessage = error?.message || 'Unable to load dashboard'; } finally { loading = false; } });
	let paidCount = $derived(recentInvoices.filter((invoice) => invoice.status === 'paid').length);
</script>
<Nav />
<main class="app-page">
	<header class="page-heading"><div><p class="eyebrow">WORKSPACE</p><h1>Good day 👋</h1><p>Here is a quick look at your inventory activity.</p></div><a class="btn btn-primary" href="/invoices/create">＋ New invoice</a></header>
	<div class="stat-grid">
		<div class="stat-card"><span>Recent invoices</span><strong>{recentInvoices.length}</strong></div>
		<div class="stat-card"><span>Paid</span><strong>{paidCount}</strong></div>
		<div class="stat-card"><span>Awaiting payment</span><strong>{recentInvoices.length - paidCount}</strong></div>
		<div class="stat-card"><span>Quick action</span><strong class="quick">Ready</strong></div>
	</div>
	<section class="panel">
		<div class="panel-title"><h2>Recent invoices</h2><a href="/invoices">View all →</a></div>
		{#if loading}<div class="state">Loading recent activity…</div>{:else if errorMessage}<div class="state error">{errorMessage}</div>{:else if recentInvoices.length === 0}<div class="state"><h3>No invoices yet</h3><p>Create your first invoice to see activity here.</p></div>{:else}
			<table class="data-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>{#each recentInvoices as invoice (invoice.id)}<tr><td class="strong">{invoice.invoice_no}</td><td>{invoice.customer?.name || 'Walk-in customer'}</td><td>{invoice.issued_date}</td><td>RM {invoice.total_amount}</td><td><span class="badge {invoice.status}">{invoice.status === 'paid' ? 'Paid' : 'Unpaid'}</span></td></tr>{/each}</tbody></table>
		{/if}
	</section>
</main>
<style>.quick { color: #315ee7 !important; font-size: 20px !important; }</style>

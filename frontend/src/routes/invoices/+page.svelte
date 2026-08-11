<script>
	import { onMount } from 'svelte'; import { api } from '$lib/api.js'; import Nav from '$lib/Nav.svelte';
	let invoices = $state([]), loading = $state(true), errorMessage = $state(''), search = $state('');
	async function loadInvoices() { loading = true; errorMessage = ''; try { const result = await api.getInvoices(); invoices = result.data ?? result ?? []; } catch (error) { errorMessage = error?.message || 'Unable to load invoices'; } finally { loading = false; } }
	let filtered = $derived(invoices.filter((invoice) => `${invoice.invoice_no} ${invoice.customer?.name || ''}`.toLowerCase().includes(search.toLowerCase())));
	onMount(loadInvoices);
</script>
<Nav /><main class="app-page"><header class="page-heading"><div><p class="eyebrow">SALES</p><h1>Invoices</h1><p>Create, review and track rental invoices.</p></div><a class="btn btn-primary" href="/invoices/create">＋ Create invoice</a></header>
<div class="toolbar"><input class="control search" placeholder="Search invoice or customer…" bind:value={search}/><button class="btn" onclick={loadInvoices}>Refresh</button></div>
<section class="panel">{#if loading}<div class="state">Loading invoices…</div>{:else if errorMessage}<div class="state error">{errorMessage}</div>{:else if filtered.length === 0}<div class="state"><h3>No invoices found</h3><p>Try another search or create a new invoice.</p></div>{:else}<table class="data-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Issued date</th><th>Amount</th><th>Status</th></tr></thead><tbody>{#each filtered as invoice (invoice.id)}<tr><td class="strong">{invoice.invoice_no}</td><td>{invoice.customer?.name || 'Walk-in customer'}</td><td>{invoice.issued_date}</td><td>RM {invoice.total_amount ?? '0.00'}</td><td><span class="badge {invoice.status}">{invoice.status}</span></td></tr>{/each}</tbody></table>{/if}</section></main>

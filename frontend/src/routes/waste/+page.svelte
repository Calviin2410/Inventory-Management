<script>
	import { onMount } from "svelte";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import DateInput from "$lib/DateInput.svelte";
	import { api } from "$lib/api.js";
	import { formatDate } from "$lib/format.js";

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");
	let fromDate = $state("");
	let toDate = $state("");
	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalInvoices = $state(0);
	let totalAmountReceived = $state(0);

	async function loadWasteSales(page = 1) {
		loading = true;
		errorMessage = "";
		try {
			const params = { page };
			if (search.trim()) params.search = search.trim();
			if (fromDate) params.from_date = fromDate;
			if (toDate) params.to_date = toDate;
			const result = await api.getWasteSales(params);
			invoices = result?.data ?? [];
			currentPage = result?.current_page ?? 1;
			lastPage = result?.last_page ?? 1;
			totalInvoices = result?.total ?? 0;
			totalAmountReceived = result?.total_amount_received ?? 0;
		} catch (error) {
			errorMessage = error?.message || "Unable to load waste sales.";
		} finally {
			loading = false;
		}
	}

	function formatAmount(value) {
		if (value === null || value === undefined || value === "") return "—";
		return new Intl.NumberFormat("en-MY", {
			style: "currency",
			currency: "MYR",
		}).format(Number(value));
	}

	onMount(() => loadWasteSales());
</script>

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">WASTE SALES</p>
			<h1>Waste</h1>
			<p>Record money received when collected waste is sold.</p>
		</div>
	</header>

	<div class="toolbar waste-toolbar">
		<div class="filter-grid">
			<label class="search-filter"><span>Invoice or Barrel</span><div class="search-field">
				<input
					class="control search"
					type="search"
					placeholder="Search invoice or barrel code..."
					bind:value={search}
					onkeydown={(event) => event.key === "Enter" && loadWasteSales(1)}
				/>
				{#if search}<button type="button" class="search-clear" aria-label="Clear search" onclick={() => { search = ""; loadWasteSales(1); }}>×</button>{/if}
				</div>
			</label>
		<div class="date-filter"><span>Received From</span><DateInput bind:value={fromDate} ariaLabel="Received from date" /></div>
		<div class="date-filter"><span>Received To</span><DateInput bind:value={toDate} ariaLabel="Received to date" /></div>
			<div class="filter-actions">
				<button class="btn btn-primary" type="button" onclick={() => loadWasteSales(1)}>Search</button>
				<button class="btn" type="button" onclick={() => { search = ""; fromDate = ""; toDate = ""; loadWasteSales(1); }}>Refresh</button>
			</div>
		</div>
		<div class="filter-summary">{totalInvoices} {totalInvoices === 1 ? "result" : "results"}</div>
	</div>

	<section class="amount-summary" aria-label="Total waste sale amount received">
		<div class="amount-icon">RM</div>
		<div><span>Total Amount Received</span><strong>{formatAmount(totalAmountReceived)}</strong></div>
	</section>

	<section class="panel waste-panel">
		{#if loading}
			<SkeletonTable rows={6} columns={6} />
		{:else if errorMessage}
			<div class="state error">{errorMessage}</div>
		{:else if invoices.length === 0}
			<div class="state"><h3>No invoices found</h3><p>Try another invoice or barrel code.</p></div>
		{:else}
			<div class="table-card">
				<table class="data-table">
					<thead><tr>
						<th>Invoice Number</th>
						<th>Barrel Code</th>
						<th>Invoice Date</th>
						<th>Received Date</th>
						<th>Total Amount</th>
						<th>Remark</th>
						<th>Received By</th>
					</tr></thead>
					<tbody>
						{#each invoices as invoice (invoice.id)}
							<tr>
								<td><a class="invoice-link" href={`/invoices/${invoice.id}`}>{invoice.invoice_no}</a></td>
								<td>{invoice.items?.map((item) => item.barrel?.code).filter(Boolean).join(", ") || "—"}</td>
								<td>{formatDate(invoice.issued_date)}</td>
								<td>{formatDate(invoice.waste_sale_recorded_at)}</td>
								<td class="amount-cell">{formatAmount(invoice.waste_sale_amount)}</td>
								<td class="remark-cell">{invoice.waste_sale_remark || "—"}</td>
								<td>{invoice.waste_sale_recorded_by?.name || "—"}</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>

			<div class="pagination">
				<div class="pagination-info">Page {currentPage} of {lastPage}<span>{totalInvoices} invoices</span></div>
				<div class="pagination-actions">
					<button type="button" disabled={currentPage <= 1 || loading} onclick={() => loadWasteSales(currentPage - 1)}>Previous</button>
					<button type="button" disabled={currentPage >= lastPage || loading} onclick={() => loadWasteSales(currentPage + 1)}>Next</button>
				</div>
			</div>
		{/if}
	</section>

</main>

<style>
	.waste-toolbar { display: block; padding: 16px; }
	.filter-grid { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(175px, 220px) minmax(175px, 220px) auto; align-items: end; gap: 12px; }
	.search-filter, .date-filter { display: grid; gap: 6px; min-width: 0; }
	.search-filter span, .date-filter span { color: #536078; font-size: 12px; font-weight: 700; }
	.search { width: 100%; max-width: none; }
	.filter-actions { display: flex; gap: 8px; }
	.filter-summary { margin-top: 12px; color: #7e899b; font-size: 11px; font-weight: 600; }
	.amount-summary { display: flex; align-items: center; gap: 14px; width: min(100%, 360px); margin: 0 0 20px auto; padding: 16px 18px; border: 1px solid #b9ead9; border-radius: 11px; background: #f2fcf8; }
	.amount-icon { display: grid; width: 42px; height: 42px; place-items: center; flex: none; border-radius: 50%; background: #d5f6e9; color: #087a55; font-size: 11px; font-weight: 800; }
	.amount-summary div:last-child { display: grid; gap: 3px; }
	.amount-summary span { color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
	.amount-summary strong { color: #087a55; font-size: 22px; }
	.waste-panel { overflow: hidden; }
	.table-card { width: 100%; max-width: 100%; overflow-x: auto; overscroll-behavior-inline: contain; -webkit-overflow-scrolling: touch; }
	.data-table { min-width: 900px; }
	.invoice-link { color: #172033; font-weight: 700; text-decoration: none; }
	.invoice-link:hover { color: #2554c7; text-decoration: underline; }
	.amount-cell { color: #087a55; font-weight: 700; white-space: nowrap; }
	.remark-cell { max-width: 280px; color: #536078; line-height: 1.45; white-space: normal; }
	.state { padding: 36px 24px; color: #64748b; text-align: center; }
	.state h3 { margin: 0 0 6px; color: #111827; }
	.state p { margin: 0; }
	.state.error { color: #b42318; }
	@media (max-width: 1280px) {
		.filter-grid { grid-template-columns: minmax(250px, 1fr) repeat(2, minmax(170px, 1fr)); }
		.filter-actions { grid-column: 1 / -1; }
		.data-table th:first-child, .data-table td:first-child { position: sticky; left: 0; z-index: 2; background: white; box-shadow: 8px 0 12px -12px rgb(15 23 42 / 45%); }
		.data-table th:first-child { z-index: 3; background: #f7f9fb; }
		.data-table th:last-child, .data-table td:last-child { position: sticky; right: 0; z-index: 2; background: white; box-shadow: -8px 0 12px -12px rgb(15 23 42 / 45%); }
		.data-table th:last-child { z-index: 3; background: #f7f9fb; }
	}
	@media (max-width: 720px) {
		.filter-grid { grid-template-columns: 1fr; }
		.filter-actions { grid-column: auto; }
		.filter-actions .btn { flex: 1; }
		.amount-summary { width: 100%; margin-left: 0; }
	}
</style>

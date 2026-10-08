<script>
	import { onMount } from "svelte";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import Toast from "$lib/Toast.svelte";
	import { api } from "$lib/api.js";
	import { formatDate } from "$lib/format.js";

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let successMessage = $state("");
	let search = $state("");
	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalInvoices = $state(0);
	let selectedInvoice = $state(null);
	let amount = $state("");
	let remark = $state("");
	let saving = $state(false);
	let dialogError = $state("");

	async function loadWasteSales(page = 1) {
		loading = true;
		errorMessage = "";
		try {
			const params = { page };
			if (search.trim()) params.search = search.trim();
			const result = await api.getWasteSales(params);
			invoices = result?.data ?? [];
			currentPage = result?.current_page ?? 1;
			lastPage = result?.last_page ?? 1;
			totalInvoices = result?.total ?? 0;
		} catch (error) {
			errorMessage = error?.message || "Unable to load waste sales.";
		} finally {
			loading = false;
		}
	}

	function openSaleDialog(invoice) {
		selectedInvoice = invoice;
		amount = invoice.waste_sale_amount ?? "";
		remark = invoice.waste_sale_remark ?? "";
		dialogError = "";
	}

	function closeSaleDialog() {
		if (!saving) selectedInvoice = null;
	}

	async function saveWasteSale() {
		if (!selectedInvoice || saving) return;
		saving = true;
		dialogError = "";
		try {
			const updated = await api.updateWasteSale(selectedInvoice.id, {
				amount: Number(amount),
				remark: remark.trim() || null,
			});
			invoices = invoices.map((invoice) =>
				invoice.id === updated.id ? updated : invoice,
			);
			selectedInvoice = null;
			successMessage = "Waste sale saved successfully.";
		} catch (error) {
			dialogError = error?.errors?.amount?.[0] || error?.message || "Unable to save waste sale.";
		} finally {
			saving = false;
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

	<div class="toolbar">
		<input
			class="control search"
			type="search"
			placeholder="Search invoice or barrel code..."
			bind:value={search}
			onkeydown={(event) => event.key === "Enter" && loadWasteSales(1)}
		/>
		<button class="btn" type="button" onclick={() => loadWasteSales(1)}>Search</button>
		<button class="btn" type="button" onclick={() => { search = ""; loadWasteSales(1); }}>Refresh</button>
		<span class="result-count">{totalInvoices} {totalInvoices === 1 ? "result" : "results"}</span>
	</div>

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
						<th>Total Amount</th>
						<th>Remark</th>
						<th class="action-column"></th>
					</tr></thead>
					<tbody>
						{#each invoices as invoice (invoice.id)}
							<tr>
								<td><a class="invoice-link" href={`/invoices/${invoice.id}`}>{invoice.invoice_no}</a></td>
								<td>{invoice.items?.map((item) => item.barrel?.code).filter(Boolean).join(", ") || "—"}</td>
								<td>{formatDate(invoice.issued_date)}</td>
								<td class="amount-cell">{formatAmount(invoice.waste_sale_amount)}</td>
								<td class="remark-cell">{invoice.waste_sale_remark || "—"}</td>
								<td class="action-cell">
									<button class="record-button" type="button" onclick={() => openSaleDialog(invoice)}>
										{invoice.waste_sale_amount === null ? "Record Sale" : "Update Sale"}
									</button>
								</td>
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

	{#if selectedInvoice}
		<div class="dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && closeSaleDialog()}>
			<form class="sale-dialog" onsubmit={(event) => { event.preventDefault(); saveWasteSale(); }}>
				<h2>{selectedInvoice.waste_sale_amount === null ? "Record Waste Sale" : "Update Waste Sale"}</h2>
				<p>{selectedInvoice.invoice_no}</p>
				{#if dialogError}<div class="dialog-error" role="alert">{dialogError}</div>{/if}
				<label>Amount (RM)<input type="number" min="0" max="9999999999.99" step="0.01" bind:value={amount} required /></label>
				<label>Remark <small>Optional</small><textarea rows="4" maxlength="1000" bind:value={remark}></textarea></label>
				<div class="dialog-actions">
					<button class="btn" type="button" onclick={closeSaleDialog} disabled={saving}>Cancel</button>
					<button class="btn btn-primary" type="submit" disabled={saving}>{saving ? "Saving…" : "Save Sale"}</button>
				</div>
			</form>
		</div>
	{/if}

	<Toast message={successMessage} onclose={() => (successMessage = "")} />
</main>

<style>
	.search { flex: 1 1 320px; max-width: 560px; }
	.waste-panel { overflow: hidden; }
	.table-card { width: 100%; max-width: 100%; overflow-x: auto; overscroll-behavior-inline: contain; -webkit-overflow-scrolling: touch; }
	.data-table { min-width: 900px; }
	.invoice-link { color: #172033; font-weight: 700; text-decoration: none; }
	.invoice-link:hover { color: #2554c7; text-decoration: underline; }
	.amount-cell { color: #087a55; font-weight: 700; white-space: nowrap; }
	.remark-cell { max-width: 280px; color: #536078; line-height: 1.45; white-space: normal; }
	.action-column { width: 130px; }
	.action-cell { text-align: right; }
	.record-button { min-height: 36px; padding: 0 13px; border: 1px solid #8ed8c0; border-radius: 8px; background: #effcf7; color: #087a55; font-size: 12px; font-weight: 750; cursor: pointer; white-space: nowrap; }
	.state { padding: 36px 24px; color: #64748b; text-align: center; }
	.state h3 { margin: 0 0 6px; color: #111827; }
	.state p { margin: 0; }
	.state.error { color: #b42318; }
	.dialog-layer { position: fixed; inset: 0; z-index: 2100; display: grid; place-items: center; padding: 20px; background: rgb(15 23 42 / 48%); }
	.sale-dialog { width: min(100%, 440px); padding: 24px; border-radius: 12px; background: white; box-shadow: 0 24px 70px rgb(15 23 42 / 24%); }
	.sale-dialog h2 { margin: 0 0 6px; color: #172033; }
	.sale-dialog > p { margin: 0 0 18px; color: #64748b; }
	.sale-dialog label { display: grid; gap: 7px; margin-top: 15px; color: #344054; font-size: 13px; font-weight: 700; }
	.sale-dialog label small { color: #8a94a6; font-weight: 500; }
	.sale-dialog input, .sale-dialog textarea { width: 100%; padding: 11px 12px; border: 1px solid #ccd4e0; border-radius: 9px; outline: none; background: white; font: inherit; font-weight: 400; }
	.sale-dialog textarea { resize: vertical; }
	.sale-dialog input:focus, .sale-dialog textarea:focus { border-color: #315ee7; box-shadow: 0 0 0 3px rgb(49 94 231 / 12%); }
	.dialog-error { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b42318; }
	.dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
	@media (max-width: 1280px) {
		.data-table th:first-child, .data-table td:first-child { position: sticky; left: 0; z-index: 2; background: white; box-shadow: 8px 0 12px -12px rgb(15 23 42 / 45%); }
		.data-table th:first-child { z-index: 3; background: #f7f9fb; }
		.data-table th:last-child, .data-table td:last-child { position: sticky; right: 0; z-index: 2; background: white; box-shadow: -8px 0 12px -12px rgb(15 23 42 / 45%); }
		.data-table th:last-child { z-index: 3; background: #f7f9fb; }
	}
	@media (max-width: 560px) { .dialog-actions { flex-direction: column-reverse; } .dialog-actions .btn { width: 100%; } }
</style>

<script>
	import { onMount } from "svelte";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import DateInput from "$lib/DateInput.svelte";
	import { api } from "$lib/api.js";
	import { formatDate } from "$lib/format.js";
	import WasteSaleDialog from "$lib/WasteSaleDialog.svelte";
	import { user } from "$lib/stores/auth.js";
	import ColumnPicker from "$lib/ColumnPicker.svelte";
	import FilterSelect from "$lib/FilterSelect.svelte";
	import { loadPageAndScrollToTop } from "$lib/pagination.js";

	const wasteColumns = [
		{ key: "invoiceNumber", label: "Invoice Number" },
		{ key: "invoiceDate", label: "Invoice Date" },
		{ key: "customer", label: "Customer" },
		{ key: "barrelCode", label: "Barrel" },
		{ key: "wasteSellAmount", label: "Sell Waste Amount" },
		{ key: "sellDate", label: "Sell Date" },
		{ key: "createdBy", label: "Created By" },
	];
	let visibleColumns = $state(
		Object.fromEntries(
			wasteColumns.map((column) => [column.key, true]),
		),
	);

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");
	let searchBy = $state("all");
	let fromDate = $state("");
	let toDate = $state("");
	let receivedByFilter = $state("");
	let wasteStatusFilter = $state("");
	let receivedByOptions = $state([
		{ value: "", label: "All received by" },
	]);
	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalInvoices = $state(0);
	let totalAmountReceived = $state(0);
	let invoiceSortDirection = $state("");
	let invoiceDateSortDirection = $state("desc");
	let saleInvoice = $state(null);
	let isAdmin = $derived($user?.role === "admin");
	function canManageInvoice(invoice) {
		return invoice?.settlement_status !== "settled" && (isAdmin || ["staff", "normal_staff"].includes($user?.role));
	}

	async function loadWasteSales(page = 1) {
		loading = true;
		errorMessage = "";
		try {
			const params = { page };
			if (invoiceSortDirection) { params.sort = "invoice_no"; params.direction = invoiceSortDirection; }
			else if (invoiceDateSortDirection) { params.sort = "issued_date"; params.direction = invoiceDateSortDirection; }
			if (search.trim()) {
				params.search = search.trim();
				if (searchBy !== "all") params.search_by = searchBy;
			}
			if (fromDate) params.from_date = fromDate;
			if (toDate) params.to_date = toDate;
			if (receivedByFilter) params.received_by = receivedByFilter;
			if (wasteStatusFilter) params.waste_status = wasteStatusFilter;
			const result = await api.getWasteSales(params);
			invoices = result?.data ?? [];
			receivedByOptions = [
				{ value: "", label: "All received by" },
				...(result?.received_by_options ?? []).map((receiver) => ({
					value: String(receiver.id),
					label: receiver.name,
				})),
			];
			currentPage = result?.current_page ?? 1;
			lastPage = result?.last_page ?? 1;
			totalInvoices = result?.total ?? 0;
			totalAmountReceived = result?.total_amount_received ?? 0;
		} catch (error) {
			errorMessage = error?.message || "Unable to load Sell Waste.";
		} finally {
			loading = false;
		}
	}

	function toggleSort(column) {
		if (column === "invoice_no") {
			invoiceSortDirection = invoiceSortDirection === "asc" ? "desc" : "asc";
			invoiceDateSortDirection = "";
		} else {
			invoiceDateSortDirection = invoiceDateSortDirection === "asc" ? "desc" : "asc";
			invoiceSortDirection = "";
		}
		loadWasteSales(1);
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
			<p class="eyebrow">SELL WASTE</p>
			<h1>Waste</h1>
			<p>Record money received when collected waste is sold.</p>
		</div>
	</header>

	<div class="toolbar waste-toolbar">
		<div class="filter-grid">
			<label class="search-filter"
				><div class="search-controls">
					<FilterSelect compact className="search-scope" ariaLabel="Search field" bind:value={searchBy} options={[{ value: "all", label: "All fields" }, { value: "invoice", label: "Invoice" }, { value: "barrel", label: "Barrel" }]} />
				<div class="search-field">
					<input
						class="control search"
						type="search"
						placeholder={searchBy === "invoice" ? "Search invoice number..." : searchBy === "barrel" ? "Search barrel code..." : "Search invoice or barrel code..."}
						bind:value={search}
						onkeydown={(event) =>
							event.key === "Enter" && loadWasteSales(1)}
					/>
					{#if search}<button
							type="button"
							class="search-clear"
							aria-label="Clear search"
							onclick={() => {
								search = "";
								loadWasteSales(1);
							}}>×</button
						>{/if}
				</div>
				</div>
			</label>
			<FilterSelect
				bind:value={receivedByFilter}
				options={receivedByOptions}
				ariaLabel="Filter Sell Waste by receiver"
				onchange={() => loadWasteSales(1)}
			/>
			<FilterSelect
				bind:value={wasteStatusFilter}
				options={[
					{ value: "", label: "All Sell Waste" },
					{ value: "unsold", label: "Not sold" },
					{ value: "sold", label: "Sold" },
				]}
				ariaLabel="Filter invoices by Sell Waste status"
				onchange={() => loadWasteSales(1)}
			/>
			<div class="filter-actions">
				<button
					class="btn btn-primary"
					type="button"
					onclick={() => loadWasteSales(1)}>Search</button
				>
				<button
					class="btn"
					type="button"
					onclick={() => {
						search = "";
						searchBy = "all";
						fromDate = "";
						toDate = "";
						receivedByFilter = "";
						wasteStatusFilter = "";
						loadWasteSales(1);
					}}>Refresh</button
				>
			</div>
			<ColumnPicker columns={wasteColumns} bind:visible={visibleColumns} />
		</div>
	</div>

	<div class="waste-overview-row">
		<section
			class="amount-summary"
			aria-label="Total Sell Waste amount received"
		>
			<div class="amount-icon">RM</div>
			<div>
				<span>Total Sell Waste Amount</span><strong
					>{formatAmount(totalAmountReceived)}</strong
				>
			</div>
		</section>
		<div class="date-range-filters" aria-label="Filter invoices by date range">
			<label class="date-filter">
				<span>Invoice From</span><DateInput
					bind:value={fromDate}
					max={toDate}
					onchange={() => loadWasteSales(1)}
					ariaLabel="Invoice from date"
				/>
			</label>
			<label class="date-filter">
				<span>Invoice To</span><DateInput
					bind:value={toDate}
					min={fromDate}
					onchange={() => loadWasteSales(1)}
					ariaLabel="Invoice to date"
				/>
			</label>
		</div>
	</div>

	<section class="panel waste-panel">
		{#if loading}
			<SkeletonTable rows={6} columns={8} />
		{:else if errorMessage}
			<div class="state error">{errorMessage}</div>
		{:else if invoices.length === 0}
			<div class="state">
				<h3>No invoices found</h3>
				<p>Try another invoice or barrel code.</p>
			</div>
		{:else}
			<div class="table-card">
				<table class="data-table">
					<thead
						><tr>
							{#if visibleColumns.invoiceNumber}<th aria-sort={invoiceSortDirection === "asc" ? "ascending" : invoiceSortDirection === "desc" ? "descending" : "none"}><button class="sort-header" type="button" onclick={() => toggleSort("invoice_no")}>Invoice Number <span aria-hidden="true">{invoiceSortDirection === "asc" ? "↑" : invoiceSortDirection === "desc" ? "↓" : "↕"}</span></button></th>{/if}
							{#if visibleColumns.invoiceDate}<th aria-sort={invoiceDateSortDirection === "asc" ? "ascending" : invoiceDateSortDirection === "desc" ? "descending" : "none"}><button class="sort-header" type="button" onclick={() => toggleSort("issued_date")}>Invoice Date <span aria-hidden="true">{invoiceDateSortDirection === "asc" ? "↑" : invoiceDateSortDirection === "desc" ? "↓" : "↕"}</span></button></th>{/if}
							{#if visibleColumns.customer}<th>Customer</th>{/if}
							{#if visibleColumns.barrelCode}<th>Barrel</th>{/if}
							{#if visibleColumns.wasteSellAmount}<th>Sell Waste Amount</th>{/if}
							{#if visibleColumns.sellDate}<th>Sell Date</th>{/if}
							{#if visibleColumns.createdBy}<th>Created By</th>{/if}
							<th>Action Status</th>
						</tr></thead
					>
					<tbody>
						{#each invoices as invoice (invoice.id)}
							<tr>
								{#if visibleColumns.invoiceNumber}<td
									><a
										class="invoice-link"
										href={`/invoices/${invoice.id}`}
										>{invoice.invoice_no}</a
									></td>{/if}
								{#if visibleColumns.invoiceDate}<td>{formatDate(invoice.issued_date)}</td>{/if}
								{#if visibleColumns.customer}<td>{invoice.customer?.name ?? "—"}</td>{/if}
								{#if visibleColumns.barrelCode}<td
									>{invoice.items
										?.map((item) => item.barrel?.code)
										.filter(Boolean)
										.join(", ") || "—"}</td>{/if}
								{#if visibleColumns.wasteSellAmount}<td class="amount-cell"
									>{formatAmount(
						invoice.waste_sell_amount ?? 0,
									)}</td>{/if}
								{#if visibleColumns.sellDate}<td
									>{formatDate(
										invoice.waste_sell_recorded_at,
									)}</td>{/if}
								{#if visibleColumns.createdBy}<td
									>{invoice.waste_sell_recorded_by?.name ||
										"—"}</td>{/if}
				<td
					>{#if canManageInvoice(invoice)}<button
											class="btn update-sale-button"
											type="button"
											onclick={() =>
												(saleInvoice = invoice)}
							>{invoice.waste_sell_amount === null ? "Sell Waste" : "Update Sell"}</button
										>{:else}—{/if}</td
								>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>

			<div class="pagination">
				<div class="pagination-info">
					Page {currentPage} of {lastPage}<span
						>{totalInvoices} invoices</span
					>
				</div>
				<div class="pagination-actions">
					<button
						type="button"
						disabled={currentPage <= 1 || loading}
						onclick={() => loadPageAndScrollToTop(() => loadWasteSales(currentPage - 1))}
						>Previous</button
					>
					<button
						type="button"
						disabled={currentPage >= lastPage || loading}
						onclick={() => loadPageAndScrollToTop(() => loadWasteSales(currentPage + 1))}
						>Next</button
					>
				</div>
			</div>
		{/if}
	</section>
</main>

{#if saleInvoice}
	<WasteSaleDialog
		invoice={saleInvoice}
		oncancel={() => (saleInvoice = null)}
		onsaved={(updated) => {
			invoices = invoices.map((invoice) =>
				invoice.id === updated.id ? updated : invoice,
			);
			saleInvoice = null;
			loadWasteSales(currentPage);
		}}
	/>
{/if}

<style>
	.waste-toolbar {
		display: block;
		padding: 16px;
	}
	.filter-grid {
		display: grid;
		grid-template-columns: minmax(350px, 1.3fr) minmax(175px, 210px) minmax(170px, 205px) auto auto;
		align-items: end;
		gap: 12px;
	}
	.search-filter,
	.date-filter {
		display: grid;
		gap: 6px;
		min-width: 0;
	}
	.search-controls { display: flex; gap: 8px; min-width: 0; }
	:global(.search-scope) { width: 130px; flex: 0 0 130px; }
	.search-field { position: relative; flex: 1; min-width: 0; }
	.date-range-filters {
		display: grid;
		grid-template-columns: repeat(2, minmax(175px, 220px));
		align-items: end;
		gap: 12px;
	}
	.date-filter span {
		color: #536078;
		font-size: 12px;
		font-weight: 700;
	}
	.search {
		width: 100%;
		max-width: none;
	}
	.filter-actions {
		display: flex;
		gap: 8px;
	}
	.amount-summary {
		display: flex;
		align-items: center;
		gap: 14px;
		width: min(100%, 360px);
		margin: 0;
		padding: 16px 18px;
		border: 1px solid #b9ead9;
		border-radius: 11px;
		background: #f2fcf8;
	}
	.waste-overview-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 20px;
		margin: 24px 0 20px;
	}
	.amount-icon {
		display: grid;
		width: 42px;
		height: 42px;
		place-items: center;
		flex: none;
		border-radius: 50%;
		background: #d5f6e9;
		color: #087a55;
		font-size: 11px;
		font-weight: 800;
	}
	.amount-summary div:last-child {
		display: grid;
		gap: 3px;
	}
	.amount-summary span {
		color: #64748b;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.04em;
	}
	.amount-summary strong {
		color: #087a55;
		font-size: 22px;
	}
	.waste-panel {
		overflow: hidden;
	}
	.table-card {
		width: 100%;
		max-width: 100%;
		overflow-x: auto;
		overscroll-behavior-inline: contain;
		-webkit-overflow-scrolling: touch;
	}
	.data-table {
		min-width: 900px;
	}
	.invoice-link {
		color: #172033;
		font-weight: 700;
		text-decoration: none;
	}
	.invoice-link:hover {
		color: #2554c7;
		text-decoration: underline;
	}
	.update-sale-button {
		white-space: nowrap;
	}
	.amount-cell {
		color: #087a55;
		font-weight: 700;
		white-space: nowrap;
	}
	.state {
		padding: 36px 24px;
		color: #64748b;
		text-align: center;
	}
	.state h3 {
		margin: 0 0 6px;
		color: #111827;
	}
	.state p {
		margin: 0;
	}
	.state.error {
		color: #b42318;
	}
	@media (max-width: 1280px) {
		.filter-grid {
			grid-template-columns: repeat(3, minmax(0, 1fr));
		}
		.search-filter {
			grid-column: span 2;
		}
		.filter-actions {
			grid-column: auto;
		}
		:global(.column-picker) {
			grid-column: auto;
			justify-self: end;
		}
		.data-table th:first-child,
		.data-table td:first-child {
			position: sticky;
			left: 0;
			z-index: 2;
			background: white;
			box-shadow: 8px 0 12px -12px rgb(15 23 42 / 45%);
		}
		.data-table th:first-child {
			z-index: 3;
			background: #f7f9fb;
		}
	}
	@media (max-width: 720px) {
		.filter-grid {
			grid-template-columns: 1fr;
		}
		.search-filter {
			grid-column: auto;
		}
		.search-controls { flex-direction: column; }
		:global(.search-scope) { width: 100%; flex-basis: auto; }
		.filter-actions {
			grid-column: auto;
		}
		:global(.column-picker) {
			grid-column: auto;
			justify-self: stretch;
		}
		.filter-actions .btn {
			flex: 1;
		}
		.amount-summary {
			width: 100%;
		}
		.waste-overview-row {
			flex-direction: column;
			align-items: stretch;
		}
		.date-range-filters {
			width: 100%;
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}
</style>

<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import ColumnPicker from "$lib/ColumnPicker.svelte";
	import FilterSelect from "$lib/FilterSelect.svelte";

	const customerColumns = [
		{ key: "customer", label: "Customer" },
		{ key: "phone", label: "Phone" },
		{ key: "recordId", label: "Record ID" },
	];
	let visibleColumns = $state(Object.fromEntries(customerColumns.map((column) => [column.key, true])));

	let customers = $state([]);

	let loading = $state(true);
	let errorMessage = $state("");

	let search = $state("");
	let searchBy = $state("all");

	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalCustomers = $state(0);

	function getCustomerPhones(customer) {
		const phones = customer.phone_numbers?.length
			? customer.phone_numbers
			: customer.phone ? [customer.phone] : [];
		return phones.join(", ") || "—";
	}

	async function loadCustomers(page = 1) {
		loading = true;
		errorMessage = "";

		try {
			const params = {
				page,
			};

			if (search.trim()) {
				params.search = search.trim();
				if (searchBy !== "all") params.search_by = searchBy;
			}

			const result = await api.getCustomers(params);

			customers = result?.data ?? [];

			currentPage = result?.current_page ?? 1;

			lastPage = result?.last_page ?? 1;

			totalCustomers = result?.total ?? 0;
		} catch (error) {
			errorMessage = error?.message || "Unable to load customers";
		} finally {
			loading = false;
		}
	}

	async function previousPage() {
		if (currentPage <= 1) {
			return;
		}

		await loadCustomers(currentPage - 1);
	}

	async function nextPage() {
		if (currentPage >= lastPage) {
			return;
		}

		await loadCustomers(currentPage + 1);
	}

	onMount(() => {
		loadCustomers(1);
	});
</script>

<Nav />

<main class="app-page">
	<!-- =========================
	     HEADER
	========================= -->

	<header class="page-heading">
		<div>
			<p class="eyebrow">DIRECTORY</p>

			<h1>Customers</h1>

			<p>Customer details collected from your invoices.</p>
		</div>
	</header>

	<!-- =========================
	     TOOLBAR
	========================= -->

	<div class="toolbar">
		<FilterSelect compact className="search-scope" ariaLabel="Search field" bind:value={searchBy} options={[{ value: "all", label: "All fields" }, { value: "name", label: "Name" }, { value: "phone", label: "Phone" }]} />
		<div class="search-field"><input
			class="control search"
			type="text"
			placeholder={searchBy === "name" ? "Search customer name..." : searchBy === "phone" ? "Search phone number..." : "Search name or phone..."}
			bind:value={search}
			onkeydown={(event) => {
				if (event.key === "Enter") {
					loadCustomers(1);
				}
			}}
		/>
		{#if search}
			<button type="button" class="search-clear" aria-label="Clear search" onclick={() => { search = ""; loadCustomers(1); }}>×</button>
		{/if}
		</div>

		<button type="button" class="btn" onclick={() => loadCustomers(1)}>
			Search
		</button>

		<button
			type="button"
			class="btn"
			onclick={() => {
				search = "";
				searchBy = "all";
				loadCustomers(1);
			}}
		>
			Refresh
		</button>
		<ColumnPicker columns={customerColumns} bind:visible={visibleColumns} />
	</div>

	<!-- =========================
	     CUSTOMER TABLE
	========================= -->

	<section class="panel">
		{#if loading}
			<SkeletonTable rows={5} columns={3} />
		{:else if errorMessage}
			<div class="state error">
				{errorMessage}
			</div>
		{:else if customers.length === 0}
			<div class="state">
				{#if search.trim()}
					<h3>No matching customers found</h3>

					<p>Try another name or phone number.</p>
				{:else}
					<h3>No customers found</h3>

					<p>Customers will appear after an invoice is created.</p>
				{/if}
			</div>
		{:else}
			<div class="table-card">
				<table class="data-table">
					<thead>
						<tr>
							{#if visibleColumns.customer}<th> Customer </th>{/if}

							{#if visibleColumns.phone}<th> Phone </th>{/if}

							{#if visibleColumns.recordId}<th> Record ID </th>{/if}
						</tr>
					</thead>

					<tbody>
						{#each customers as customer (customer.id)}
							<tr>
								{#if visibleColumns.customer}<td class="strong">
									<span class="avatar">
										{customer.name?.[0]?.toUpperCase() || "W"}
									</span>

									<a
										class="customer-link"
										href={`/customers/${customer.id}`}
									>
										{customer.name || "-"}
									</a>
								</td>{/if}

								{#if visibleColumns.phone}<td>
					<span class="customer-phones">{getCustomerPhones(customer)}</span>
								</td>{/if}

								{#if visibleColumns.recordId}<td>
									#{customer.id}
								</td>{/if}
							</tr>
						{/each}
					</tbody>
				</table>
			</div>

			<!-- =========================
			     PAGINATION
			========================= -->

			<div class="pagination">
				<div class="pagination-info">
					Page {currentPage}
					of {lastPage}

					<span>
						{totalCustomers}

						{totalCustomers === 1 ? "customer" : "customers"}
					</span>
				</div>

				<div class="pagination-actions">
					<button
						type="button"
						onclick={previousPage}
						disabled={currentPage <= 1}
					>
						Previous
					</button>

					<button
						type="button"
						onclick={nextPage}
						disabled={currentPage >= lastPage}
					>
						Next
					</button>
				</div>
			</div>
		{/if}
	</section>
</main>

<style>
	/* =========================
	   CUSTOMER
	========================= */

	.strong {
		font-weight: 600;
	}

	.avatar {
		display: inline-grid;
		place-items: center;

		width: 30px;
		height: 30px;

		margin-right: 10px;

		border-radius: 8px;

		background: #eef2ff;
		color: #315ee7;

		font-size: 12px;
		font-weight: 700;
	}

	.customer-link {
		color: #1e293b;
		text-decoration: none;
	}

	.customer-link:hover {
		color: #315ee7;
		text-decoration: underline;
	}

	.customer-phones { overflow-wrap: anywhere; }

	/* =========================
	   STATES
	========================= */

	.state {
		padding: 28px;

		color: #64748b;

		font-size: 14px;
		text-align: center;
	}

	.state h3 {
		margin: 0 0 6px;

		color: #111827;
	}

	.state p {
		margin: 0;
	}

	.error {
		color: #dc2626;
	}
</style>

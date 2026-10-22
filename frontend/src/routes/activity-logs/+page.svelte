<script>
	import { onMount } from "svelte";
	import { goto } from "$app/navigation";

	import { api } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import DateInput from "$lib/DateInput.svelte";
	import FilterSelect from "$lib/FilterSelect.svelte";
	import { formatDate } from "$lib/format.js";
	import ColumnPicker from "$lib/ColumnPicker.svelte";

	const activityColumns = [
		{ key: "dateTime", label: "Date & Time" },
		{ key: "user", label: "User" },
		{ key: "action", label: "Action" },
		{ key: "record", label: "Record" },
		{ key: "activity", label: "Activity" },
	];
	let visibleColumns = $state(Object.fromEntries(activityColumns.map((column) => [column.key, true])));

	let logs = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");
	let searchBy = $state("all");
	let action = $state("");
	let subjectType = $state("");
	let fromDate = $state("");
	let toDate = $state("");
	let currentPage = $state(1);
	let lastPage = $state(1);

	const actionLabels = {
		created: "Created",
		updated: "Updated",
		deleted: "Deleted",
		returned: "Returned",
		password_reset: "Password reset",
		settled: "Settled",
		reopened: "Reopened",
		waste_sell_recorded: "Waste sell recorded",
		waste_sell_updated: "Waste sell updated",
		waste_sale_recorded: "Waste sale recorded",
		waste_sale_updated: "Waste sale updated",
	};

	const actionOptions = [
		{ value: "", label: "All actions" },
		{ value: "created", label: "Created" },
		{ value: "updated", label: "Updated" },
		{ value: "returned", label: "Returned" },
		{ value: "deleted", label: "Deleted" },
		{ value: "password_reset", label: "Password reset" },
		{ value: "waste_sell_recorded", label: "Waste sell recorded" },
		{ value: "waste_sell_updated", label: "Waste sell updated" },
		{ value: "waste_sale_recorded", label: "Waste sale recorded (legacy logs)" },
		{ value: "waste_sale_updated", label: "Waste sale updated (legacy logs)" },
	];

	const recordTypeOptions = [
		{ value: "", label: "All record types" },
		{ value: "Invoice", label: "Invoices" },
		{ value: "Customer", label: "Customers" },
		{ value: "Barrel", label: "Barrels" },
		{ value: "Vehicle", label: "Vehicles" },
		{ value: "Staff", label: "Staff" },
	];

	function formatDateTime(value) {
		if (!value) return "—";

		return new Intl.DateTimeFormat("en-MY", {
			dateStyle: "medium",
			timeStyle: "short",
			timeZone: "Asia/Kuala_Lumpur",
		}).format(new Date(value));
	}

	function formatValues(values, actorName = "") {
		if (!values || Object.keys(values).length === 0) return "None";

		return Object.entries(values)
			.map(
				([key, value]) =>
					`${key.replaceAll("_", " ")}: ${formatChangeValue(key, value, actorName)}`,
			)
			.join("\n");
	}

	function formatChangeValue(key, value, actorName = "") {
		if (value === null || value === undefined || value === "") return "—";

		if (key === "items" && Array.isArray(value)) {
			return value
				.map((item, index) => {
					if (!item || typeof item !== "object") {
						return `Item ${index + 1}: ${String(item)}`;
					}

					const barrel = item.barrel ?? item.barrel_code ?? item.barrel_id ?? "Unknown";
					const rentalDate = (date) => {
						if (!date) return "—";
						const dateOnly = String(date).match(/^\d{4}-\d{2}-\d{2}/)?.[0];
						return formatDate(dateOnly || date);
					};

					return [
						`Barrel ${barrel}`,
						`Rental start: ${rentalDate(item.rental_start)}`,
						`Rental end: ${rentalDate(item.rental_end)}`,
					].join("\n");
				})
				.join("\n\n");
		}

		if (["issued_date", "payment_date"].includes(key)) {
			const dateOnly = String(value).match(/^\d{4}-\d{2}-\d{2}/)?.[0];
			return formatDate(dateOnly || value);
		}

		if (key === "settled_at") {
			return formatDateTime(value);
		}

		if (key === "settled_by") {
			return typeof value === "number" || /^\d+$/.test(String(value))
				? actorName || `User #${value}`
				: String(value);
		}

		if (key === "payment_method") {
			return value === "bank_in" ? "Bank In" : "Cash";
		}

		return String(value);
	}

	function formatRecordLabel(log) {
		const label = log.subject_label || `#${log.subject_id}`;

		if (log.subject_type === "Vehicle" && log.subject_id) {
			return `${label} (ID: ${log.subject_id})`;
		}

		return label;
	}

	async function loadLogs(pageNumber = 1) {
		loading = true;
		errorMessage = "";

		try {
			const params = { page: pageNumber };

			if (search.trim()) {
				params.search = search.trim();
				if (searchBy !== "all") params.search_by = searchBy;
			}
			if (action) params.action = action;
			if (subjectType) params.subject_type = subjectType;
			if (fromDate) params.from_date = fromDate;
			if (toDate) params.to_date = toDate;

			const result = await api.getActivityLogs(params);

			logs = result?.data ?? [];
			currentPage = result?.current_page ?? 1;
			lastPage = result?.last_page ?? 1;
		} catch (error) {
			errorMessage = error?.message || "Unable to load activity logs.";
		} finally {
			loading = false;
		}
	}

	function clearFilters() {
		search = "";
		searchBy = "all";
		action = "";
		subjectType = "";
		fromDate = "";
		toDate = "";
		loadLogs(1);
	}

	onMount(async () => {
		try {
			const currentUser = await api.me();
			user.set(currentUser);

			if (currentUser?.role !== "admin") {
				goto("/dashboard");
				return;
			}

			await loadLogs();
		} catch (error) {
			goto("/login");
		}
	});
</script>

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">SECURITY & AUDIT</p>
			<h1>Activity Log</h1>
			<p>Review important changes made by administrators and staff.</p>
		</div>
	</header>

	<section class="filter-card" aria-label="Activity log filters">
		<div class="search-row">
		<FilterSelect compact className="search-scope" ariaLabel="Search field" bind:value={searchBy} options={[{ value: "all", label: "All fields" }, { value: "user", label: "User" }, { value: "record", label: "Record" }, { value: "activity", label: "Activity" }]} />
		<div class="search-wrap">
			<input
				class="control"
				type="search"
				placeholder={searchBy === "user" ? "Search user..." : searchBy === "record" ? "Search record..." : searchBy === "activity" ? "Search activity..." : "Search user, record or activity..."}
				bind:value={search}
				onkeydown={(event) => event.key === "Enter" && loadLogs(1)}
			/>
			{#if search}
				<button
					type="button"
					aria-label="Clear search"
					onclick={() => {
						search = "";
						loadLogs(1);
					}}>×</button
				>
			{/if}
		</div>
		</div>

		<FilterSelect
			bind:value={action}
			options={actionOptions}
			onchange={() => loadLogs(1)}
			ariaLabel="Filter by action"
		/>

		<FilterSelect
			bind:value={subjectType}
			options={recordTypeOptions}
			onchange={() => loadLogs(1)}
			ariaLabel="Filter by record type"
		/>

		<button class="btn" type="button" onclick={() => loadLogs(1)}
			>Search</button
		>
		<button class="btn" type="button" onclick={clearFilters}>Refresh</button
		>
	</section>

	<section class="panel">
		<div class="activity-date-range" aria-label="Filter activity by date range">
			<label>
				<span>From</span><DateInput
					bind:value={fromDate}
					max={toDate}
					onchange={() => loadLogs(1)}
					ariaLabel="Select activity from date"
				/>
			</label>
			<label>
				<span>To</span><DateInput
					bind:value={toDate}
					min={fromDate}
					onchange={() => loadLogs(1)}
					ariaLabel="Select activity to date"
				/>
			</label>
			<ColumnPicker columns={activityColumns} bind:visible={visibleColumns} />
		</div>
		{#if loading}
			<SkeletonTable rows={7} columns={5} />
		{:else if errorMessage}
			<div class="state error">{errorMessage}</div>
		{:else if logs.length === 0}
			<div class="state">
				<h3>No activities found</h3>
				<p>Important changes will appear here.</p>
			</div>
		{:else}
			<div class="table-card">
				<table class="data-table">
					<thead>
						<tr>
							{#if visibleColumns.dateTime}<th>Date & Time</th>{/if}
							{#if visibleColumns.user}<th>User</th>{/if}
							{#if visibleColumns.action}<th>Action</th>{/if}
							{#if visibleColumns.record}<th>Record</th>{/if}
							{#if visibleColumns.activity}<th>Activity</th>{/if}
						</tr>
					</thead>
					<tbody>
						{#each logs as log (log.id)}
							<tr>
								{#if visibleColumns.dateTime}<td class="date-cell"
									>{formatDateTime(log.created_at)}</td
								>{/if}
								{#if visibleColumns.user}<td>
									<strong
										>{log.actor_name ||
											"Deleted user"}</strong
									>
									<small>{log.actor_email || "—"}</small>
								</td>{/if}
								{#if visibleColumns.action}<td
									><span class="action-badge {log.action}"
										>{actionLabels[log.action] ||
											log.action}</span
									></td
								>{/if}
								{#if visibleColumns.record}<td>
									<strong>{log.subject_type}</strong>
									<small>{formatRecordLabel(log)}</small>
								</td>{/if}
								{#if visibleColumns.activity}<td class="activity-cell">
									<span>{log.description}</span>
									{#if log.old_values || log.new_values}
										<details>
											<summary>View changes</summary>
											<div class="change-grid">
												<div>
													<strong>Before</strong>
													<pre>{formatValues(
															log.old_values,
															log.actor_name,
														)}</pre>
												</div>
												<div>
													<strong>After</strong>
													<pre>{formatValues(
															log.new_values,
															log.actor_name,
														)}</pre>
												</div>
											</div>
										</details>
									{/if}
								</td>{/if}
							</tr>
						{/each}
					</tbody>
				</table>
			</div>

			<div class="pagination">
				<div class="pagination-info">
					Page {currentPage} of {lastPage}
				</div>
				<div class="pagination-actions">
					<button
						type="button"
						disabled={currentPage <= 1 || loading}
						onclick={() => loadLogs(currentPage - 1)}
						>Previous</button
					>
					<button
						type="button"
						disabled={currentPage >= lastPage || loading}
						onclick={() => loadLogs(currentPage + 1)}>Next</button
					>
				</div>
			</div>
		{/if}
	</section>
</main>

<style>
	.filter-card {
		display: grid;
		grid-template-columns:
			minmax(380px, 1fr) repeat(2, minmax(145px, auto))
			auto auto;
		align-items: end;
		gap: 10px;
		margin-bottom: 12px;
		padding: 14px;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		background: #fff;
	}
	.search-wrap {
		position: relative;
	}
	.search-row { display: flex; gap: 8px; min-width: 0; }
	:global(.search-scope) { width: 132px; flex: 0 0 132px; }
	.search-row .search-wrap { flex: 1; min-width: 0; }
	.search-wrap .control {
		width: 100%;
		padding-right: 42px;
	}
	.search-wrap button {
		position: absolute;
		top: 5px;
		right: 5px;
		display: grid;
		width: 32px;
		height: 32px;
		place-items: center;
		border: 0;
		border-radius: 7px;
		background: #f0f3f7;
		color: #64748b;
		cursor: pointer;
	}
	.activity-date-range {
		display: flex;
		justify-content: flex-end;
		gap: 12px;
		padding: 10px 16px;
		border-bottom: 1px solid #e5e7eb;
		background: #f8fafc;
	}
	.activity-date-range > label {
		display: grid;
		width: 190px;
		gap: 4px;
		color: #596579;
		font-size: 11px;
		font-weight: 700;
	}
	.activity-date-range :global(.column-picker) { align-self: flex-end; }
	.state {
		padding: 40px 24px;
		color: #64748b;
		text-align: center;
	}
	.state h3 {
		margin: 0 0 7px;
		color: #111827;
	}
	.state p {
		margin: 0;
	}
	.state.error {
		color: #b42318;
	}
	td strong,
	td small {
		display: block;
	}
	td small {
		margin-top: 3px;
		color: #8490a2;
		font-size: 10px;
	}
	.date-cell {
		width: 155px;
		color: #475569;
		white-space: nowrap;
	}
	.action-badge {
		display: inline-flex;
		padding: 5px 9px;
		border-radius: 999px;
		background: #eef2f7;
		color: #526074;
		font-size: 10px;
		font-weight: 750;
		white-space: nowrap;
	}
	.action-badge.created {
		background: #dcfce7;
		color: #166534;
	}
	.action-badge.updated,
	.action-badge.returned {
		background: #e8efff;
		color: #2450b7;
	}
	.action-badge.deleted {
		background: #fee2e2;
		color: #b91c1c;
	}
	.action-badge.password_reset {
		background: #fff1d6;
		color: #926516;
	}
	.activity-cell {
		min-width: 260px;
	}
	details {
		margin-top: 7px;
	}
	summary {
		color: #315ee7;
		font-size: 10px;
		font-weight: 700;
		cursor: pointer;
	}
	.change-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 8px;
		margin-top: 8px;
	}
	.change-grid > div {
		padding: 9px;
		border-radius: 7px;
		background: #f7f9fc;
	}
	.change-grid strong {
		color: #64748b;
		font-size: 9px;
		text-transform: uppercase;
	}
	pre {
		margin: 5px 0 0;
		overflow-wrap: anywhere;
		color: #334155;
		font: 10px/1.55 inherit;
		white-space: pre-wrap;
	}
	@media (max-width: 1150px) {
		.filter-card {
			grid-template-columns: repeat(3, minmax(0, 1fr));
		}
		.search-row {
			grid-column: span 3;
		}
	}
	@media (max-width: 900px) {
		.filter-card {
			grid-template-columns: 1fr;
		}
		.search-row {
			grid-column: auto;
		}
		.search-row { flex-direction: column; }
		:global(.search-scope) { width: 100%; flex-basis: auto; }
		.activity-date-range { flex-wrap: wrap; }
	}
	@media (max-width: 560px) {
		.activity-date-range { align-items: stretch; flex-direction: column; }
		.activity-date-range > label { width: 100%; }
		:global(.column-picker),
		:global(.column-picker summary) { width: 100%; }
		.filter-card .control,
		.filter-card .btn {
			width: 100%;
		}
		.change-grid {
			grid-template-columns: 1fr;
		}
		.data-table {
			min-width: 900px;
		}
	}
</style>

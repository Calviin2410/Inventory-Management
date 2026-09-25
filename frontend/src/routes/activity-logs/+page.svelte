<script>
	import { onMount } from "svelte";
	import { goto } from "$app/navigation";

	import { api } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import DateInput from "$lib/DateInput.svelte";
	import { formatDate } from "$lib/format.js";

	let logs = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");
	let action = $state("");
	let subjectType = $state("");
	let fromDate = $state("");
	let toDate = $state("");
	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalLogs = $state(0);

	const actionLabels = {
		created: "Created",
		updated: "Updated",
		deleted: "Deleted",
		returned: "Returned",
		password_reset: "Password reset",
		settled: "Settled",
		reopened: "Reopened",
	};

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

		if (
			["Driver", "Vehicle"].includes(log.subject_type) &&
			log.subject_id
		) {
			return `${label} (ID: ${log.subject_id})`;
		}

		return label;
	}

	async function loadLogs(pageNumber = 1) {
		loading = true;
		errorMessage = "";

		try {
			const params = { page: pageNumber };

			if (search.trim()) params.search = search.trim();
			if (action) params.action = action;
			if (subjectType) params.subject_type = subjectType;
			if (fromDate) params.from_date = fromDate;
			if (toDate) params.to_date = toDate;

			const result = await api.getActivityLogs(params);

			logs = result?.data ?? [];
			currentPage = result?.current_page ?? 1;
			lastPage = result?.last_page ?? 1;
			totalLogs = result?.total ?? 0;
		} catch (error) {
			errorMessage = error?.message || "Unable to load activity logs.";
		} finally {
			loading = false;
		}
	}

	function clearFilters() {
		search = "";
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
		<div class="search-wrap">
			<input
				class="control"
				type="search"
				placeholder="Search user, record or activity..."
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

		<select
			class="control"
			bind:value={action}
			onchange={() => loadLogs(1)}
			aria-label="Filter by action"
		>
			<option value="">All actions</option>
			<option value="created">Created</option>
			<option value="updated">Updated</option>
			<option value="returned">Returned</option>
			<option value="deleted">Deleted</option>
			<option value="password_reset">Password reset</option>
		</select>

		<select
			class="control"
			bind:value={subjectType}
			onchange={() => loadLogs(1)}
			aria-label="Filter by record type"
		>
			<option value="">All record types</option>
			<option value="Invoice">Invoices</option>
			<option value="Customer">Customers</option>
			<option value="Barrel">Barrels</option>
			<option value="Driver">Drivers</option>
			<option value="Vehicle">Vehicles</option>
			<option value="Staff">Staff</option>
		</select>

		<label
			><span>From</span><DateInput
				bind:value={fromDate}
				max={toDate}
				onchange={() => loadLogs(1)}
				ariaLabel="Select activity from date"
			/></label
		>
		<label
			><span>To</span><DateInput
				bind:value={toDate}
				min={fromDate}
				onchange={() => loadLogs(1)}
				ariaLabel="Select activity to date"
			/></label
		>

		<button class="btn" type="button" onclick={() => loadLogs(1)}
			>Search</button
		>
		<button class="btn" type="button" onclick={clearFilters}>Clear</button>
	</section>

	<div class="count">
		{totalLogs}
		{totalLogs === 1 ? "activity" : "activities"}
	</div>

	<section class="panel">
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
							<th>Date & Time</th>
							<th>User</th>
							<th>Action</th>
							<th>Record</th>
							<th>Activity</th>
						</tr>
					</thead>
					<tbody>
						{#each logs as log (log.id)}
							<tr>
								<td class="date-cell"
									>{formatDateTime(log.created_at)}</td
								>
								<td>
									<strong
										>{log.actor_name ||
											"Deleted user"}</strong
									>
									<small>{log.actor_email || "—"}</small>
								</td>
								<td
									><span class="action-badge {log.action}"
										>{actionLabels[log.action] ||
											log.action}</span
									></td
								>
								<td>
									<strong>{log.subject_type}</strong>
									<small>{formatRecordLabel(log)}</small>
								</td>
								<td class="activity-cell">
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
								</td>
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
		grid-template-columns: minmax(240px, 1fr) repeat(2, minmax(145px, auto)) repeat(
				2,
				minmax(145px, auto)
			) auto auto;
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
	.filter-card label {
		display: flex;
		flex-direction: column;
		gap: 5px;
	}
	.filter-card label span {
		color: #64748b;
		font-size: 10px;
		font-weight: 700;
	}
	.count {
		margin: 0 4px 14px;
		color: #7e899b;
		font-size: 11px;
		font-weight: 600;
	}
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
		.search-wrap {
			grid-column: span 3;
		}
	}
	@media (max-width: 700px) {
		.filter-card {
			grid-template-columns: 1fr;
		}
		.search-wrap {
			grid-column: auto;
		}
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

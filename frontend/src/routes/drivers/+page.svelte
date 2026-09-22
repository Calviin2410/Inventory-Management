<script>
	import { onMount } from "svelte";
	import { goto } from "$app/navigation";

	import { api } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";
	import Nav from "$lib/Nav.svelte";
	import ConfirmDialog from "$lib/ConfirmDialog.svelte";
	import Toast from "$lib/Toast.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";

	let drivers = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");

	let name = $state("");
	let phone = $state("");
	let saving = $state(false);

	let openMenuId = $state(null);
	let updatingStatusId = $state(null);
	let selectedDriver = $state(null);
	let deletingDriverId = $state(null);
	let deleteError = $state("");
	let successMessage = $state("");

	let showAddForm = $state(false);

	let isAdmin = $derived($user?.role === "admin");

	async function loadDrivers() {
		loading = true;
		errorMessage = "";

		try {
			const result = await api.getDrivers();

			drivers = Array.isArray(result) ? result : (result?.data ?? []);
		} catch (error) {
			errorMessage = error?.message || "Unable to load drivers.";
		} finally {
			loading = false;
		}
	}

	async function handleSubmit(event) {
		event.preventDefault();

		if (!isAdmin || saving) {
			return;
		}

		if (!name.trim()) {
			errorMessage = "Please enter the driver name.";
			return;
		}

		saving = true;
		errorMessage = "";

		try {
			const created = await api.createDriver({
				name: name.trim(),
				phone: phone.trim() || null,
			});

			drivers = [...drivers, created].sort((a, b) =>
				a.name.localeCompare(b.name),
			);

			name = "";
			phone = "";
			showAddForm = false;
		} catch (error) {
			errorMessage = error?.message || "Unable to add driver.";
		} finally {
			saving = false;
		}
	}

	async function updateDriverStatus(driver) {
		if (!isAdmin || updatingStatusId !== null) {
			return;
		}

		updatingStatusId = driver.id;
		errorMessage = "";

		const nextStatus =
			driver.status === "available"
				? "unavailable"
				: "available";

		try {
			const updated = await api.updateDriver(driver.id, {
				status: nextStatus,
			});

			drivers = drivers.map((item) =>
				item.id === driver.id ? updated : item,
			);

			openMenuId = null;
		} catch (error) {
			errorMessage =
				error?.message || "Unable to update driver status.";
		} finally {
			updatingStatusId = null;
		}
	}

	function requestDriverDeletion(driver) {
		selectedDriver = driver;
		deleteError = "";
	}

	function closeDeleteDialog() {
		if (deletingDriverId === null) {
			selectedDriver = null;
			deleteError = "";
		}
	}

	async function deleteDriver() {
		const driver = selectedDriver;

		if (!driver || deletingDriverId !== null) {
			return;
		}

		deletingDriverId = driver.id;
		deleteError = "";

		try {
			await api.deleteDriver(driver.id);

			drivers = drivers.filter((item) => item.id !== driver.id);
			selectedDriver = null;
			successMessage = `Driver ${driver.name} deleted successfully.`;

			setTimeout(() => {
				successMessage = "";
			}, 3500);
		} catch (error) {
			deleteError = error?.message || "Unable to delete driver.";
		} finally {
			deletingDriverId = null;
		}
	}

	onMount(async () => {
		try {
			const currentUser = await api.me();

			user.set(currentUser);

			if (currentUser?.role !== "admin") {
				goto("/dashboard");
				return;
			}

			await loadDrivers();
		} catch (error) {
			goto("/login");
		}
	});
</script>

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">TRANSPORT</p>

			<h1>Drivers</h1>

			<p>Manage drivers used for invoice deliveries.</p>
		</div>

		{#if isAdmin}
			<button
				type="button"
				class="btn btn-primary"
				onclick={() => {
					showAddForm = !showAddForm;
				}}
			>
				{showAddForm ? "Close" : "+ Add driver"}
			</button>
		{/if}
	</header>

	{#if isAdmin && showAddForm}
		<section class="panel add-panel">
			<h2>Add driver</h2>

			<form class="driver-form" onsubmit={handleSubmit}>
				<label>
					<span> Driver Name </span>

					<input
						type="text"
						placeholder="e.g. Bryan"
						bind:value={name}
						required
					/>
				</label>

				<label>
					<span> Phone </span>

					<input
						type="text"
						placeholder="e.g. 012-345 6789"
						bind:value={phone}
					/>
				</label>

				<div class="form-actions">
					<button
						type="button"
						class="btn"
						onclick={() => {
							showAddForm = false;

							name = "";
							phone = "";
						}}
					>
						Cancel
					</button>

					<button
						type="submit"
						class="btn btn-primary"
						disabled={saving}
					>
						{saving ? "Saving…" : "Save driver"}
					</button>
				</div>
			</form>
		</section>
	{/if}

	{#if errorMessage}
		<div class="error-message">
			{errorMessage}
		</div>
	{/if}

	<section class="panel">
		{#if loading}
			<SkeletonTable rows={4} columns={4} />
		{:else if drivers.length === 0}
			<div class="state">
				<h3>No drivers yet</h3>

				<p>Click Add Driver to create your first driver.</p>
			</div>
		{:else}
			<table class="data-table driver-table">
				<thead>
					<tr>
						<th> Driver </th>
						<th> Phone </th>
						<th>Status</th>
   						<th class="action-column">Action</th>
					</tr>
				</thead>

				<tbody>
					{#each drivers as driver (driver.id)}
						<tr>
							<td class="strong">
								{driver.name}
							</td>

							<td>
								{driver.phone || "—"}
							</td>

							<td>
								<span
									class="status-badge"
									class:available={driver.status === "available"}
									class:unavailable={driver.status === "unavailable"}
								>
									{driver.status === "available"
										? "Available"
										: "Unavailable"}
								</span>
							</td>

							<td class="action-cell">
								<div class="action-menu">
									<button
										type="button"
										class="more-button"
										aria-label="Driver actions"
										onclick={() => {
											openMenuId =
												openMenuId === driver.id
													? null
													: driver.id;
										}}
									>
										•••
									</button>

									{#if openMenuId === driver.id}
										<div class="dropdown-menu">
											<button
												type="button"
												class="dropdown-item"
												onclick={() =>
													goto(`/drivers/${driver.id}/edit`)}
											>
												Edit Driver
											</button>

											<button
												type="button"
												class="dropdown-item"
												disabled={updatingStatusId === driver.id}
												onclick={() => updateDriverStatus(driver)}
											>
												{#if updatingStatusId === driver.id}
													Updating...
												{:else if driver.status === "available"}
													Set Unavailable
												{:else}
													Set Available
												{/if}
											</button>

											<button
												type="button"
												class="dropdown-item delete-item"
												onclick={() => requestDriverDeletion(driver)}
											>
												Delete Driver
											</button>
										</div>
									{/if}
								</div>
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		{/if}
	</section>
</main>

<ConfirmDialog
	open={selectedDriver !== null}
	title="Delete this driver?"
	message="Please confirm that you want to remove this driver. Existing invoices will be kept, but will no longer be linked to this driver."
	itemName={selectedDriver?.name ?? ""}
	confirmLabel="Delete driver"
	busy={deletingDriverId !== null}
	errorMessage={deleteError}
	oncancel={closeDeleteDialog}
	onconfirm={deleteDriver}
/>

<Toast message={successMessage} onclose={() => (successMessage = "")} />

<style>
	.add-panel {
		margin-bottom: 24px;
		padding: 24px;
	}

	.add-panel h2 {
		margin: 0 0 18px;
		font-size: 18px;
	}

	.driver-form {
		display: grid;

		grid-template-columns:
			minmax(0, 1fr)
			minmax(0, 1fr);

		gap: 16px;
	}

	label {
		display: flex;
		flex-direction: column;
		gap: 7px;
	}

	label span {
		color: #344054;
		font-size: 14px;
		font-weight: 600;
	}

	input {
		width: 100%;
		min-height: 42px;

		box-sizing: border-box;

		padding: 10px 12px;

		border: 1px solid #d0d5dd;
		border-radius: 8px;

		background: white;
		color: #101828;

		font: inherit;
	}

	input:focus {
		outline: none;

		border-color: #2563eb;

		box-shadow: 0 0 0 3px rgb(37 99 235 / 10%);
	}

	.form-actions {
		display: flex;
		justify-content: flex-end;

		gap: 10px;

		grid-column: 1 / -1;

		margin-top: 4px;
	}

	.error-message {
		margin-bottom: 20px;

		padding: 12px 14px;

		border: 1px solid #fecaca;
		border-radius: 8px;

		background: #fef2f2;
		color: #b42318;
	}

	.strong {
		font-weight: 600;
	}

	.state {
		padding: 28px;

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

	.action-column,
	.action-cell {
		width: 120px;
		text-align: right;
	}

	.action-menu {
		position: relative;
		display: inline-block;
	}

	.more-button {
		width: 38px;
		height: 36px;
		border: 1px solid #d7dce5;
		border-radius: 8px;
		background: white;
		color: #536078;
		font-size: 18px;
		font-weight: 700;
		cursor: pointer;
	}

	.dropdown-menu {
		position: absolute;
		top: 42px;
		right: 0;
		z-index: 100;
		min-width: 170px;
		padding: 6px;
		border: 1px solid #e5e7eb;
		border-radius: 8px;
		background: white;
		box-shadow: 0 10px 30px rgb(15 23 42 / 14%);
	}

	.dropdown-item {
		display: block;
		width: 100%;
		padding: 10px;
		border: 0;
		border-radius: 6px;
		background: transparent;
		color: #202939;
		text-align: left;
		cursor: pointer;
	}

	.dropdown-item:hover {
		background: #f3f4f6;
	}

	.dropdown-item.delete-item {
		color: #c62828;
	}

	.dropdown-item.delete-item:hover {
		background: #fff1f1;
	}

	.status-badge {
		display: inline-flex;
		min-width: 82px;
		justify-content: center;
		padding: 5px 10px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 700;
	}

	.status-badge.available {
		background: #dcfce7;
		color: #166534;
	}

	.status-badge.unavailable {
		background: #fee2e2;
		color: #b91c1c;
	}

	@media (max-width: 760px) {
		.panel {
			overflow-x: hidden;
		}

		.driver-table {
			width: 100%;
			min-width: 0;
			table-layout: fixed;
		}

		.driver-table th:first-child,
		.driver-table td:first-child {
			position: static;
			box-shadow: none;
		}

		.driver-table th,
		.driver-table td {
			width: 50%;
			white-space: normal;
			word-break: break-word;
		}

		.driver-form {
			grid-template-columns: 1fr;
		}

		.form-actions {
			grid-column: auto;
			flex-direction: column-reverse;
		}

		.form-actions .btn {
			width: 100%;
		}
	}
</style>

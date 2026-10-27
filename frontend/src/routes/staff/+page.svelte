<script>
	import { onMount } from "svelte";
	import { goto } from "$app/navigation";

	import { api } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";
	import Nav from "$lib/Nav.svelte";
	import AddStaffDialog from "$lib/AddStaffDialog.svelte";
	import ResetPasswordDialog from "$lib/ResetPasswordDialog.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import Toast from "$lib/Toast.svelte";
	import ColumnPicker from "$lib/ColumnPicker.svelte";
	import FilterSelect from "$lib/FilterSelect.svelte";

	const staffColumns = [
		{ key: "name", label: "Staff Name" },
		{ key: "email", label: "Email" },
		{ key: "type", label: "Account Type" },
	];
	let visibleColumns = $state(Object.fromEntries(staffColumns.map((column) => [column.key, true])));

	let staff = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");
	let searchBy = $state("all");
	let selectedStaff = $state(null);
	let resetting = $state(false);
	let resetError = $state("");
	let successMessage = $state("");
	let addingStaff = $state(false);
	let creatingStaff = $state(false);
	let createError = $state("");
	let deletingStaffId = $state(null);

	let filteredStaff = $derived.by(() => {
		const keyword = search.trim().toLowerCase();

		if (!keyword) return staff;

		return staff.filter((member) => {
			const value = searchBy === "name" ? member.name : searchBy === "email" ? member.email : `${member.name} ${member.email}`;
			return value.toLowerCase().includes(keyword);
		});
	});

	async function loadStaff() {
		loading = true;
		errorMessage = "";

		try {
			const result = await api.getStaff();
			staff = Array.isArray(result) ? result : [];
		} catch (error) {
			errorMessage = error?.message || "Unable to load staff accounts.";
		} finally {
			loading = false;
		}
	}

	function requestPasswordReset(member) {
		selectedStaff = member;
		resetError = "";
	}

	function closeResetDialog() {
		if (!resetting) {
			selectedStaff = null;
			resetError = "";
		}
	}

	async function resetPassword(payload) {
		if (!selectedStaff || resetting) return;

		resetting = true;
		resetError = "";

		try {
			await api.resetStaffPassword(selectedStaff.id, payload);
			const staffName = selectedStaff.name;
			selectedStaff = null;
			successMessage = `${staffName}'s password was reset successfully.`;

			setTimeout(() => {
				successMessage = "";
			}, 3500);
		} catch (error) {
			resetError =
				error?.errors?.password?.[0] ||
				error?.message ||
				"Unable to reset this password.";
		} finally {
			resetting = false;
		}
	}

	async function createStaff(payload) {
		if (creatingStaff) return;
		creatingStaff = true;
		createError = "";

		try {
			const created = await api.createStaff(payload);
			staff = [...staff, created].sort((a, b) => a.name.localeCompare(b.name));
			addingStaff = false;
			successMessage = `${created.name}'s staff account was created successfully.`;
			setTimeout(() => (successMessage = ""), 3500);
		} catch (error) {
			createError = Object.values(error?.errors ?? {})?.[0]?.[0] || error?.message || "Unable to create staff account.";
		} finally {
			creatingStaff = false;
		}
	}

	async function deleteStaff(member) {
		if (member.role !== "normal_staff" || deletingStaffId !== null) return;
		if (!window.confirm(`Delete staff account “${member.name}” (${member.email})? This cannot be undone. Existing invoices and activity history will be kept.`)) return;

		deletingStaffId = member.id;
		errorMessage = "";
		try {
			await api.deleteStaff(member.id);
			staff = staff.filter((entry) => entry.id !== member.id);
			successMessage = `${member.name}'s staff account was deleted.`;
			setTimeout(() => (successMessage = ""), 3500);
		} catch (error) {
			errorMessage = error?.message || "Unable to delete this staff account.";
		} finally {
			deletingStaffId = null;
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

			await loadStaff();
		} catch (error) {
			goto("/login");
		}
	});
</script>

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">ACCESS MANAGEMENT</p>
			<h1>Staff</h1>
			<p>View admin and staff accounts, reset staff passwords, and remove staff access.</p>
		</div>
		<button class="btn btn-primary" type="button" onclick={() => { createError = ""; addingStaff = true; }}>+ Add Staff</button>
	</header>

	<form class="toolbar" onsubmit={(event) => event.preventDefault()}>
		<FilterSelect compact ariaLabel="Search field" bind:value={searchBy} options={[{ value: "all", label: "All fields" }, { value: "name", label: "Name" }, { value: "email", label: "Email" }]} />
		<div class="search-field"><input
			class="control search"
			type="search"
			placeholder="Search staff name or email..."
			bind:value={search}
		/>
		{#if search}<button type="button" class="search-clear" aria-label="Clear search" onclick={() => (search = "")}>×</button>{/if}
		</div>

		<button type="submit" class="btn">Search</button>
		<button type="button" class="btn" onclick={() => { search = ""; searchBy = "all"; loadStaff(); }}>Refresh</button>
		<ColumnPicker columns={staffColumns} bind:visible={visibleColumns} />

	</form>

	<section class="panel staff-panel">
		{#if loading}
			<SkeletonTable rows={5} columns={3} />
		{:else if errorMessage}
			<div class="state error">{errorMessage}</div>
		{:else if filteredStaff.length === 0}
			<div class="state">
				<h3>{search ? "No matching staff found" : "No staff accounts yet"}</h3>
				<p>{search ? "Try another name or email address." : "Registered staff accounts will appear here."}</p>
			</div>
		{:else}
			<div class="table-card">
				<table class="data-table">
					<thead>
						<tr>
							{#if visibleColumns.name}<th>Staff Name</th>{/if}
							{#if visibleColumns.email}<th>Email</th>{/if}
							{#if visibleColumns.type}<th>Account Type</th>{/if}
							<th class="action-column">Action</th>
						</tr>
					</thead>
					<tbody>
						{#each filteredStaff as member (member.id)}
							<tr>
								{#if visibleColumns.name}<td>
									<div class="staff-name">
										<span class="staff-avatar">{member.name.slice(0, 2).toUpperCase()}</span>
										<strong>{member.name}</strong>
									</div>
								</td>{/if}
								{#if visibleColumns.email}<td>{member.email}</td>{/if}
								{#if visibleColumns.type}<td><span class:admin-badge={member.role === "admin"} class="role-badge">{member.role === "admin" ? "Admin" : "Staff"}</span></td>{/if}
								<td class="action-cell">
									{#if member.role === "normal_staff"}
										<button class="btn reset-button" type="button" onclick={() => requestPasswordReset(member)}>Reset Password</button>
										<button class="btn delete-button" type="button" disabled={deletingStaffId === member.id} onclick={() => deleteStaff(member)}>{deletingStaffId === member.id ? "Deleting…" : "Delete Staff"}</button>
									{:else}
										<span class="admin-note">Protected admin account</span>
									{/if}
								</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>
		{/if}
	</section>
</main>

<ResetPasswordDialog
	open={selectedStaff !== null}
	staffName={selectedStaff?.name ?? ""}
	busy={resetting}
	errorMessage={resetError}
	oncancel={closeResetDialog}
	onconfirm={resetPassword}
/>

<AddStaffDialog
	open={addingStaff}
	busy={creatingStaff}
	errorMessage={createError}
	oncancel={() => { if (!creatingStaff) addingStaff = false; }}
	onconfirm={createStaff}
/>

<Toast message={successMessage} onclose={() => (successMessage = "")} />

<style>
	.search { flex: 1; max-width: 560px; }
	.staff-panel { overflow: visible; }
	.state { padding: 38px 24px; color: #64748b; text-align: center; }
	.state h3 { margin: 0 0 7px; color: #111827; font-size: 17px; }
	.state p { margin: 0; }
	.state.error { color: #b42318; }
	.staff-name { display: flex; align-items: center; gap: 11px; }
	.staff-avatar { display: grid; width: 34px; height: 34px; place-items: center; flex: none; border-radius: 50%; background: #e7edff; color: #244cad; font-size: 10px; font-weight: 800; }
	.staff-name strong { color: #172033; font-size: 14px; }
	.role-badge { display: inline-flex; padding: 5px 10px; border-radius: 999px; background: #eef2f7; color: #536078; font-size: 11px; font-weight: 700; }
	.action-column, .action-cell { width: 250px; text-align: right !important; }
	.reset-button { min-height: 38px; color: #244cad; }
	.delete-button { min-height: 38px; margin-left: 8px; color: #b42318; border-color: #f2c7c4; }
	.admin-badge { background: #e8efff; color: #244cad; }
	.admin-note { color: #64748b; font-size: 12px; }
	@media (max-width: 900px) {
		.data-table { min-width: 720px; }
	}
</style>

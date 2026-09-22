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

	let staff = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");
	let selectedStaff = $state(null);
	let resetting = $state(false);
	let resetError = $state("");
	let successMessage = $state("");
	let addingStaff = $state(false);
	let creatingStaff = $state(false);
	let createError = $state("");

	let filteredStaff = $derived.by(() => {
		const keyword = search.trim().toLowerCase();

		if (!keyword) return staff;

		return staff.filter((member) =>
			`${member.name} ${member.email}`.toLowerCase().includes(keyword),
		);
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
			<p>View staff accounts and securely reset their passwords.</p>
		</div>
		<button class="btn btn-primary" type="button" onclick={() => { createError = ""; addingStaff = true; }}>+ Add Staff</button>
	</header>

	<div class="toolbar">
		<input
			class="control search"
			type="search"
			placeholder="Search staff name or email..."
			bind:value={search}
		/>

		{#if search}
			<button type="button" class="search-clear" aria-label="Clear search" onclick={() => (search = "")}>×</button>
		{/if}

		<span class="result-count">
			{filteredStaff.length} {filteredStaff.length === 1 ? "staff member" : "staff members"}
		</span>
	</div>

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
							<th>Staff Name</th>
							<th>Email</th>
							<th>Account Type</th>
							<th class="action-column">Action</th>
						</tr>
					</thead>
					<tbody>
						{#each filteredStaff as member (member.id)}
							<tr>
								<td>
									<div class="staff-name">
										<span class="staff-avatar">{member.name.slice(0, 2).toUpperCase()}</span>
										<strong>{member.name}</strong>
									</div>
								</td>
								<td>{member.email}</td>
								<td><span class="role-badge">Staff</span></td>
								<td class="action-cell">
									<button class="btn reset-button" type="button" onclick={() => requestPasswordReset(member)}>
										Reset Password
									</button>
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
	.action-column, .action-cell { width: 170px; text-align: right !important; }
	.reset-button { min-height: 38px; color: #244cad; }
	@media (max-width: 760px) {
		.data-table { min-width: 720px; }
	}
</style>

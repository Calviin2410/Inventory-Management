<script>
    import { onMount } from "svelte";
    import { goto } from "$app/navigation";
    import { api } from "$lib/api.js";
    import Nav from "$lib/Nav.svelte";
    import ConfirmDialog from "$lib/ConfirmDialog.svelte";
    import Toast from "$lib/Toast.svelte";
    import SkeletonTable from "$lib/SkeletonTable.svelte";
    import { user } from "$lib/stores/auth.js";

    let vehicles = $state([]);
    let loading = $state(true);
    let errorMessage = $state("");
    let search = $state("");
    let searchKeyword = $state("");
    let openMenuId = $state(null);
    let actionMenuPosition = $state({ top: 0, left: 0 });
    let deletingVehicleId = $state(null);
    let selectedVehicle = $state(null);
    let deleteError = $state("");
    let successMessage = $state("");
    let menuVehicle = $derived(
        vehicles.find((vehicle) => vehicle.id === openMenuId) ?? null,
    );

    async function loadVehicles() {
        loading = true;
        errorMessage = "";

        try {
            const result = await api.getVehicles();

            vehicles = Array.isArray(result) ? result : (result?.data ?? []);
        } catch (error) {
            errorMessage =
                error instanceof Error
                    ? error.message
                    : "Unable to load vehicles";
        } finally {
            loading = false;
        }
    }

    let filteredVehicles = $derived(
        vehicles.filter((vehicle) => {
            const keyword = searchKeyword.trim().toLowerCase();

            const plate =
                vehicle.plate_number ??
                vehicle.plate_no ??
                vehicle.number_plate ??
                "";

            return plate.toLowerCase().includes(keyword);
        }),
    );

    function handleSearch() {
        searchKeyword = search;
    }

    function toggleMenu(vehicleId, event) {
        openMenuId = openMenuId === vehicleId ? null : vehicleId;
        if (openMenuId === null) return;

        const rect = event.currentTarget.getBoundingClientRect();
        const width = 160;
        const height = 112;
        const gap = 8;
        const padding = 8;
        const fitsBelow = rect.bottom + gap + height <= window.innerHeight - padding;

        actionMenuPosition = {
            top: fitsBelow
                ? rect.bottom + gap
                : Math.max(padding, rect.top - height - gap),
            left: Math.min(
                Math.max(padding, rect.right - width),
                window.innerWidth - width - padding,
            ),
        };
    }

    function requestVehicleDeletion(vehicle) {
        selectedVehicle = vehicle;
        deleteError = "";
        openMenuId = null;
    }

    function closeDeleteDialog() {
        if (deletingVehicleId === null) {
            selectedVehicle = null;
            deleteError = "";
        }
    }

    async function deleteVehicle() {
        const vehicle = selectedVehicle;
        if (!vehicle) return;
        deletingVehicleId = vehicle.id;

        try {
            await api.deleteVehicle(vehicle.id);

            vehicles = vehicles.filter((item) => item.id !== vehicle.id);
            selectedVehicle = null;
            successMessage = `Vehicle ${vehicle.plate_number} deleted successfully.`;
            setTimeout(() => successMessage = "", 3500);
        } catch (error) {
            deleteError =
                error instanceof Error
                    ? error.message
                    : "Unable to delete vehicle";
        } finally {
            deletingVehicleId = null;
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

            await loadVehicles();
        } catch (error) {
            goto("/login");
        }
    });

    onMount(loadVehicles);
</script>

<Nav />

<main class="app-page">
    <header class="page-heading">
        <div>
            <p class="eyebrow">VEHICLES</p>
            <h1>Vehicles</h1>
            <p>Manage vehicles used for deliveries.</p>
        </div>

        <button
            class="btn btn-primary"
            onclick={() => goto("/vehicles/create")}
        >
            + Add vehicle
        </button>
    </header>

    <div class="toolbar">
        <input
            class="control search"
            type="text"
            placeholder="Search plate number..."
            bind:value={search}
            onkeydown={(event) => {
                if (event.key === "Enter") {
                    handleSearch();
                }
            }}
        />
		{#if search}
			<button type="button" class="search-clear" aria-label="Clear search" onclick={() => { search = ""; searchKeyword = ""; }}>×</button>
		{/if}

        <button class="btn" onclick={handleSearch}> Search </button>

        <button
            class="btn"
            onclick={() => {
                search = "";
                searchKeyword = "";
                loadVehicles();
            }}
        >
            Refresh
        </button>
		<span class="result-count">{filteredVehicles.length} {filteredVehicles.length === 1 ? "result" : "results"}</span>
    </div>

    <section class="panel">
        {#if loading}
            <SkeletonTable rows={4} columns={3} />
        {:else if errorMessage}
            <div class="state error">
                {errorMessage}
            </div>
		{:else if filteredVehicles.length === 0}
			<div class="empty-state">
				<h3>No vehicles found</h3>

				<p>Click Add Vehicle to create your first vehicle.</p>
			</div>
        {:else}
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Plate Number</th>
                            <th>Status</th>
                            <th class="action-column"> Action </th>
                        </tr>
                    </thead>

                    <tbody>
                        {#each filteredVehicles as vehicle}
                            <tr>
                                <td>
                                    <div class="plate-cell">
                                        <span class="vehicle-icon"> 🚚 </span>

                                        <span class="plate-number">
                                            {vehicle.plate_number ??
                                                vehicle.plate_no ??
                                                vehicle.number_plate}
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span
                                        class:status-available={vehicle.status ===
                                            "available"}
                                        class:status-maintenance={vehicle.status ===
                                            "maintenance"}
                                        class="status-badge"
                                    >
                                        {vehicle.status === "maintenance"
                                            ? "Maintenance"
                                            : "Available"}
                                    </span>
                                </td>

                                <td class="action-cell">
                                    <div class="action-menu">
                                        <button
                                            class="more-button"
                                            aria-label="Vehicle actions"
                                            onclick={(event) =>
                                                toggleMenu(vehicle.id, event)}
                                        >
                                            •••
                                        </button>

                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

        {/if}
    </section>

    {#if menuVehicle}
        <div
            class="dropdown-menu floating-action-menu"
            style:--menu-top={`${actionMenuPosition.top}px`}
            style:--menu-left={`${actionMenuPosition.left}px`}
        >
            <button class="dropdown-item" onclick={() => goto(`/vehicles/${menuVehicle.id}/edit`)}>Edit</button>
            <button class="dropdown-item delete-item" disabled={deletingVehicleId === menuVehicle.id} onclick={() => requestVehicleDeletion(menuVehicle)}>
                {deletingVehicleId === menuVehicle.id ? "Deleting..." : "Delete"}
            </button>
        </div>
    {/if}
</main>

<ConfirmDialog
    open={selectedVehicle !== null}
    title="Delete this vehicle?"
    message="Please confirm that you want to remove this vehicle from the delivery fleet."
    itemName={selectedVehicle?.plate_number ??
        selectedVehicle?.plate_no ??
        selectedVehicle?.number_plate ??
        ""}
    confirmLabel="Delete vehicle"
    busy={deletingVehicleId !== null}
    errorMessage={deleteError}
    oncancel={closeDeleteDialog}
    onconfirm={deleteVehicle}
/>
<Toast message={successMessage} onclose={() => successMessage = ""} />

<style>
    .table-card {
        overflow: visible;
    }

    .search {
        flex: 1;
        max-width: 520px;
    }

    .plate-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .vehicle-icon {
        display: grid;
        place-items: center;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #eff6ff;
        font-size: 17px;
    }

    .plate-number {
        font-weight: 700;
        letter-spacing: 0.03em;
    }

    .action-column {
        width: 100px;
        text-align: right !important;
    }

    .action-cell {
        text-align: right;
    }

    .more-button {
        width: 34px;
        height: 34px;
        border: 1px solid transparent;
        border-radius: 7px;
        background: transparent;
        color: #64748b;
        font-size: 18px;
        font-weight: 700;
        cursor: pointer;
    }

    .more-button:hover {
        border-color: #e5e7eb;
        background: #f8fafc;
        color: #111827;
    }

    .state {
        padding: 32px;
        color: #64748b;
        text-align: center;
        font-size: 14px;
    }

    .error {
        color: #dc2626;
    }

	.empty-state {
		padding: 28px;
		color: #64748b;
		font-size: 14px;
		text-align: center;
	}

    .empty-state h3 {
        margin: 0 0 7px;
        color: #111827;
	}

    .empty-state p {
		margin: 0;
		color: #64748b;
	}
    .action-menu {
        position: relative;
        display: inline-block;
    }

    .dropdown-menu {
        position: absolute;
        top: 40px;
        right: 0;
        z-index: 20;
        min-width: 140px;
        padding: 6px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: white;
        box-shadow:
            0 10px 15px -3px rgb(0 0 0 / 10%),
            0 4px 6px -4px rgb(0 0 0 / 10%);
    }

    .floating-action-menu {
        position: fixed;
        top: var(--menu-top);
        right: auto;
        bottom: auto;
        left: var(--menu-left);
        z-index: 2000;
        width: 160px;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        width: 100%;
        padding: 9px 10px;
        border: none;
        border-radius: 6px;
        background: transparent;
        color: #374151;
        text-align: left;
        font-size: 14px;
        cursor: pointer;
    }

    .dropdown-item:hover {
        background: #f8fafc;
    }

    .delete-item {
        color: #dc2626;
    }

    .delete-item:hover {
        background: #fef2f2;
    }

    .dropdown-item:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }

    .status-available {
        background: #dcfce7;
        color: #166534;
    }

    .status-maintenance {
        background: #fef3c7;
        color: #92400e;
    }

    @media (max-width: 900px) {
        .search {
            width: 100%;
            max-width: none;
        }

        .vehicle-icon {
            display: none;
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
</style>

<script>
    import { onMount } from "svelte";
    import { goto } from "$app/navigation";
    import { api } from "$lib/api.js";
    import Nav from "$lib/Nav.svelte";

    let vehicles = $state([]);
    let loading = $state(true);
    let errorMessage = $state("");
    let search = $state("");
    let searchKeyword = $state("");

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

    onMount(loadVehicles);
</script>

<Nav />

<main class="app-page">
    <header class="page-heading">
        <div>
            <p class="eyebrow">VEHICLES</p>

            <h1>Vehicles</h1>

            <p class="subtitle">Manage vehicles used for deliveries.</p>
        </div>

        <button
            type="button"
            class="btn btn-primary"
            onclick={() => goto("/vehicles/create")}
        >
            + Add Vehicle
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

        <button type="button" class="btn" onclick={handleSearch}>
            Search
        </button>

        <button
            type="button"
            class="btn"
            onclick={() => {
                search = "";
                searchKeyword = "";
                loadVehicles();
            }}
        >
            Refresh
        </button>
    </div>

    <section class="table-card">
        {#if loading}
            <div class="message">Loading vehicles...</div>
        {:else if errorMessage}
            <div class="message error">
                {errorMessage}
            </div>
        {:else if vehicles.length === 0}
            <div class="empty-state">
                <p>No vehicles yet.</p>

                <button type="button" onclick={() => goto("/vehicles/create")}>
                    Add your first vehicle
                </button>
            </div>
        {:else if filteredVehicles.length === 0}
            <div class="empty-state">
                <p>No matching vehicles found.</p>
            </div>
        {:else}
            <table>
                <thead>
                    <tr>
                        <th> Vehicle ID </th>

                        <th> Plate Number </th>
                    </tr>
                </thead>

                <tbody>
                    {#each filteredVehicles as vehicle (vehicle.id)}
                        <tr>
                            <td>
                                {vehicle.id}
                            </td>

                            <td class="plate-number">
                                {vehicle.plate_number ??
                                    vehicle.plate_no ??
                                    vehicle.number_plate ??
                                    "-"}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        {/if}
    </section>
</main>

<style>
    /* =========================
	   VEHICLE SEARCH
	========================= */

    .search {
        width: 360px;
    }

    /* =========================
	   VEHICLE TABLE
	========================= */

    .table-card {
        overflow: hidden;

        border: 1px solid #e5e7eb;
        border-radius: 9px;

        background: white;
    }

    table {
        width: 100%;

        border-collapse: collapse;
    }

    th {
        padding: 15px 18px;

        border-bottom: 1px solid #e5e7eb;

        background: #f8fafc;

        color: #374151;

        text-align: left;

        font-size: 14px;
        font-weight: 600;
    }

    td {
        padding: 16px 18px;

        border-bottom: 1px solid #e5e7eb;

        font-size: 14px;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    tbody tr:hover {
        background: #fafafa;
    }

    .plate-number {
        font-weight: 600;
    }

    /* =========================
	   STATES
	========================= */

    .message {
        padding: 24px;

        color: #64748b;
    }

    .error {
        color: #dc2626;
    }

    .empty-state {
        padding: 36px 24px;

        text-align: center;

        color: #64748b;
    }

    .empty-state p {
        margin: 0 0 14px;
    }

    .empty-state button {
        padding: 9px 16px;

        border: none;
        border-radius: 6px;

        background: #315ee7;
        color: white;

        cursor: pointer;
    }

    /* =========================
	   RESPONSIVE
	========================= */

    @media (max-width: 760px) {
        .search {
            width: 100%;
        }
    }
</style>

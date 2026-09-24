<script>
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { getToken } from '$lib/api.js';

	onMount(() => {
		goto(getToken() ? '/dashboard' : '/login');
	});
</script>

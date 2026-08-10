<script>
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';

	onMount(() => {
		const hasToken = typeof localStorage !== 'undefined' && localStorage.getItem('token');
		goto(hasToken ? '/dashboard' : '/login');
	});
</script>

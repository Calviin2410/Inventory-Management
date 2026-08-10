import { writable } from 'svelte/store';

// 当前登录用户,null 表示未登录
export const user = writable(null);

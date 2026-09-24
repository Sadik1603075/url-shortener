import { useSyncExternalStore } from 'react';

const listeners = new Set();

let state = {
  token: localStorage.getItem('auth_token'),
  user: JSON.parse(localStorage.getItem('auth_user') || 'null'),
};

function emit() {
  listeners.forEach((listener) => listener());
}

export const authStore = {
  getSnapshot() {
    return state;
  },

  subscribe(listener) {
    listeners.add(listener);

    return () => listeners.delete(listener);
  },

  login(token, user) {
    localStorage.setItem('auth_token', token);
    localStorage.setItem('auth_user', JSON.stringify(user));

    state = {
      token,
      user,
    };

    emit();
  },

  logout() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');

    state = {
      token: null,
      user: null,
    };

    emit();
  },
};

export function useAuth() {
  return useSyncExternalStore(
    authStore.subscribe,
    authStore.getSnapshot,
  );
}
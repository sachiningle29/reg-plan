import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loaded: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    role: (state) => state.user?.role ?? null,
  },

  actions: {
    async fetchUser() {
      try {
        const { data } = await axios.get('/api/me');
        this.user = data;
      } catch {
        this.user = null;
      } finally {
        this.loaded = true;
      }
    },

    async logout() {
      await axios.post('/api/logout');
      this.user = null;
    },
  },
});

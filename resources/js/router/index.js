import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

import Home from '../pages/Home.vue';
import AdminLogin from '../pages/AdminLogin.vue';
import UserLogin from '../pages/UserLogin.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
import AdminDashboard from '../pages/admin/AdminDashboard.vue';
import UserDashboard from '../pages/user/UserDashboard.vue';
import PlaceholderPage from '../pages/PlaceholderPage.vue';

import PlanImport from '../pages/admin/PlanImport.vue';

import RigDetails from '../pages/admin/RigDetails.vue';

import PlanRecordsEdit from '../pages/admin/PlanRecordsEdit.vue';



import AddUser from '../pages/admin/AddUser.vue';

import ManageUsers from '../pages/admin/ManageUsers.vue';

// import WellMaterialsConsumable from '../pages/admin/WellMaterialsConsumable.vue';

import WellMaterials from '../pages/admin/WellMaterials.vue';


import MaterialRequirement from '../pages/admin/MaterialRequirement.vue';


import ManageMaterialItems from '../pages/admin/ManageMaterialItems.vue';



const routes = [
  { path: '/', name: 'home', component: Home },
  { path: '/admin/login', name: 'admin-login', component: AdminLogin },
  { path: '/login', name: 'user-login', component: UserLogin },

  {
    path: '/admin',
    component: DashboardLayout,
    meta: { requiresAuth: true, role: 'admin' },
    children: [
      { path: 'dashboard', name: 'admin-dashboard', component: AdminDashboard },

      // inside the /admin children array:
      { path: 'plans/import', name: 'admin-plan-import', component: PlanImport },

      // replace this line inside the /admin children array:
      { path: 'rigs', name: 'admin-rigs', component: RigDetails },

    //   { path: 'users', name: 'admin-users', component: PlaceholderPage, props: { title: 'Manage Users' } },
    //  { path: 'users/create', name: 'admin-users-create', component: PlaceholderPage, props: { title: 'Add User' } },
    // { path: 'rigs', name: 'admin-rigs', component: PlaceholderPage, props: { title: 'Rig Details' } },

      { path: 'plans/edit', name: 'admin-plan-edit', component: PlanRecordsEdit },



      { path: 'users', name: 'admin-users', component: ManageUsers },

      { path: 'users/create', name: 'admin-users-create', component: AddUser },

    //   { path: 'well-materials', name: 'admin-well-materials', component: WellMaterials },

     { path: 'well-materials', name: 'admin-well-materials', component: WellMaterials },

     { path: 'material-requirement', name: 'admin-material-requirement', component: MaterialRequirement },

     { path: 'material-items', name: 'admin-material-items', component: ManageMaterialItems },

    ],
  },

  {
    path: '/user',
    component: DashboardLayout,
    meta: { requiresAuth: true, role: 'user' },
    children: [
      { path: 'dashboard', name: 'user-dashboard', component: UserDashboard },
      { path: 'rigs', name: 'user-rigs', component: PlaceholderPage, props: { title: 'My Rigs' } },
    ],
  },
];

// const router = createRouter({
//   history: createWebHistory(),
//   routes,
// });

//added on 9-9-26
const router = createRouter({
  history: createWebHistory(import.meta.env.VITE_APP_BASE_URL || '/'),
  routes,
});


router.beforeEach(async (to) => {
  const auth = useAuthStore();

  if (!auth.loaded) {
    await auth.fetchUser();
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return to.path.startsWith('/admin') ? { name: 'admin-login' } : { name: 'user-login' };
  }

  if (to.meta.role && auth.role !== to.meta.role) {
    return { name: 'home' };
  }

  return true;
});

export default router;

import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
  },
  {
    path: '/carriers',
    name: 'Carriers',
    component: () => import('@/views/CarrierList.vue'),
  },
  {
    path: '/carriers/:id',
    name: 'CarrierDetail',
    component: () => import('@/views/CarrierDetail.vue'),
  },
  {
    path: '/tracking',
    name: 'Tracking',
    component: () => import('@/views/TrackingList.vue'),
  },
  {
    path: '/tracking/logs',
    name: 'TrackingLogs',
    component: () => import('@/views/TrackingLogs.vue'),
  },
  {
    path: '/tracking/rollback',
    name: 'TrackingRollback',
    component: () => import('@/views/TrackingRollback.vue'),
  },
  {
    path: '/extension',
    name: 'Extension',
    component: () => import('@/views/ExtensionConfig.vue'),
  },
  {
    path: '/mapping',
    name: 'Mapping',
    component: () => import('@/views/StatusMapping.vue'),
  },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

export default router

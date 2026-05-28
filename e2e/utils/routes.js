// All known routes for Smart Parking System — used by crawler and coverage tests

export const GUEST_ROUTES = [
  '/',
  '/login',
  '/register',
  '/forgot-password',
];

export const ADMIN_ROUTES = [
  '/admin/dashboard',
  '/admin/parking-lots',
  '/admin/parking-lots/create',
  '/admin/parking-slots',
  '/admin/parking-slots/create',
  '/admin/devices',
  '/admin/devices/create',
  '/admin/users',
  '/admin/reservations',
  '/admin/reservations/create',
  '/admin/reservation-logs',
  '/admin/admin-actions',
  '/admin/parking-logs',
  '/admin/vehicles',
  '/admin/vehicles/create',
  '/admin/check-in',
  '/admin/check-out',
  '/admin/payments',
  '/admin/scan',
  '/admin/scan/history',
  '/profile',
  '/notifications',
];

export const USER_ROUTES = [
  '/user/dashboard',
  '/user/reservations',
  '/user/reservations/create',
  '/user/vehicles',
  '/user/vehicles/create',
  '/user/parking-logs',
  '/user/scan',
  '/profile',
  '/notifications',
];

export const ALL_ROUTES = {
  guest: GUEST_ROUTES,
  admin: ADMIN_ROUTES,
  user: USER_ROUTES,
};

import { createBrowserRouter } from 'react-router-dom';

import PublicLayout from '../components/layout/PublicLayout';
import AdminLayout from '../components/layout/AdminLayout';
import NotFound from '../components/common/NotFound';

import GenerateUrlPage from '../features/shortUrls/GenerateUrlPage';
import DashboardPage from '../features/shortUrls/DashboardPage';
import AccessCodesPage from '../features/accessCodes/AccessCodesPage';

import LoginPage from '../features/auth/LoginPage';
import ProtectedRoute from '../features/auth/ProtectedRoute';

export const router = createBrowserRouter([
  {
    element: <PublicLayout />,
    errorElement: <NotFound />,
    children: [
      {
        path: '/',
        element: <GenerateUrlPage />,
      },
    ],
  },

  {
    path: '/login',
    element: <LoginPage />,
  },

  {
    element: <ProtectedRoute />,
    children: [
      {
        path: '/admin',
        element: <AdminLayout />,
        children: [
          {
            index: true,
            element: <DashboardPage />,
          },
          {
            path: 'urls',
            element: <DashboardPage />,
          },
          {
            path: 'access-codes',
            element: <AccessCodesPage />,
          },
        ],
      },
    ],
  },

  {
    path: '*',
    element: <NotFound />,
  },
]);
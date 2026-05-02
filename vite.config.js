import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const hmrHost = env.VITE_HMR_HOST || 'localhost';

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/css/index.css',
                    'resources/js/index.js',
                    'resources/css/dashboard.css',
                    'resources/js/dashboard.js',
                    'resources/css/attendance.css',
                    'resources/js/attendance.js',
                    'resources/css/loans.css',
                    'resources/js/loans.js',
                    'resources/css/payroll_processing.css',
                    'resources/js/payroll_processing.js',
                    'resources/css/payslips.css',
                    'resources/js/payslips.js',
                    'resources/js/payslip_claims.js',
                    'resources/css/report.css',
                    'resources/js/report.js',
                    'resources/css/emp_records.css',
                    'resources/js/emp_records.js',
                    'resources/js/employee_cases.js',
                    'resources/css/profile_drawer.css',
                    'resources/css/settings.css',
                    'resources/js/settings.js',
                    'resources/css/styles.css',
                    'resources/js/script.js',
                ],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            hmr: {
                host: hmrHost,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});

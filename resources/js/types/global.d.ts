import { AxiosInstance } from 'axios';

declare global {
    interface Window {
        axios: AxiosInstance;
    }
}

declare module '@inertiajs/core' {
    interface PageProps {
        appUrl: string;
        currentUrl: string;
        flash?: {
            success?: string;
            error?: string;
        };
    }
}

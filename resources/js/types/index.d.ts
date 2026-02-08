export interface PageProps {
    appUrl: string;
    currentUrl: string;
    flash?: {
        success?: string;
        error?: string;
    };
    [key: string]: unknown;
}

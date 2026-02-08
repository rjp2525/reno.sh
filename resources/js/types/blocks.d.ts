export interface RichTextBlock {
    type: 'rich-text';
    data: {
        content: string;
    };
}

export interface ImageBlock {
    type: 'image';
    data: {
        image: string;
        caption?: string | null;
        alignment: 'left' | 'center' | 'right' | 'full';
    };
}

export interface ImageGalleryImage {
    image: string;
    caption?: string | null;
}

export interface ImageGalleryBlock {
    type: 'image-gallery';
    data: {
        images: ImageGalleryImage[];
        columns: number;
    };
}

export interface HeadingBlock {
    type: 'heading';
    data: {
        title: string;
        subtitle?: string | null;
        level: 'h2' | 'h3' | 'h4';
    };
}

export interface TwoColumnsBlock {
    type: 'two-columns';
    data: {
        left: string;
        right: string;
        ratio: '50-50' | '33-67' | '67-33';
    };
}

export interface CalloutBlock {
    type: 'callout';
    data: {
        type: 'info' | 'tip' | 'warning' | 'note';
        content: string;
    };
}

export interface DividerBlock {
    type: 'divider';
    data: {
        style: 'solid' | 'dashed' | 'dotted' | 'gradient';
    };
}

export interface CodeBlock {
    type: 'code';
    data: {
        language: string;
        code: string;
        filename?: string | null;
    };
}

export type ContentBlock =
    | RichTextBlock
    | ImageBlock
    | ImageGalleryBlock
    | HeadingBlock
    | TwoColumnsBlock
    | CalloutBlock
    | DividerBlock
    | CodeBlock;

import { FileSpreadsheet, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type ExportButtonsProps = {
    pdfHref?: string;
    excelHref?: string;
    onPdf?: () => void;
    onExcel?: () => void;
    disabled?: boolean;
};

export function ExportButtons({
    pdfHref,
    excelHref,
    onPdf,
    onExcel,
    disabled = false,
}: ExportButtonsProps) {
    return (
        <div className="inline-flex items-center gap-1">
            {(pdfHref || onPdf) && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        {pdfHref ? (
                            <Button
                                variant="outline"
                                size="icon"
                                asChild
                                aria-label="Ekspor PDF"
                            >
                                <a href={pdfHref}>
                                    <FileText
                                        className="size-4 text-destructive"
                                        strokeWidth={1.75}
                                    />
                                </a>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                disabled={disabled}
                                onClick={onPdf}
                                aria-label="Ekspor PDF"
                            >
                                <FileText
                                    className="size-4 text-destructive"
                                    strokeWidth={1.75}
                                />
                            </Button>
                        )}
                    </TooltipTrigger>
                    <TooltipContent>Ekspor PDF</TooltipContent>
                </Tooltip>
            )}

            {(excelHref || onExcel) && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        {excelHref ? (
                            <Button
                                variant="outline"
                                size="icon"
                                asChild
                                aria-label="Ekspor Excel"
                            >
                                <a href={excelHref}>
                                    <FileSpreadsheet
                                        className="size-4 text-success"
                                        strokeWidth={1.75}
                                    />
                                </a>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                disabled={disabled}
                                onClick={onExcel}
                                aria-label="Ekspor Excel"
                            >
                                <FileSpreadsheet
                                    className="size-4 text-success"
                                    strokeWidth={1.75}
                                />
                            </Button>
                        )}
                    </TooltipTrigger>
                    <TooltipContent>Ekspor Excel</TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}

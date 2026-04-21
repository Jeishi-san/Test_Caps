import { useState, useCallback } from 'react';
import { useForm } from '@inertiajs/react';
import { CarrierInfo } from './useCarrierManagement';
import { dataNeededBytes } from '@/utils/carrierCalculations';
import { useStegoEncode } from '@/hooks/useStegoEncode';

interface PoolCarrierInfo {
  id: number;
  name: string;
  capacity_bytes: number;
  file_path: string;
  validation_status: string;
  psnr?: number;
  is_in_use: boolean;
}

interface Document {
  id: number;
  name: string;
  extension: string;
  size: number;
}

interface UseEncodeFormParams {
  carriers: CarrierInfo[];
  setCarriers: React.Dispatch<React.SetStateAction<CarrierInfo[]>>;
  autoSelectedCarriers: PoolCarrierInfo[];
  clearAutoSelection: () => void;
  clearPreflight: () => void;
  handlePreflightCheck: (params: any) => Promise<{ passed: boolean }>;
  getPreflightParams: () => any;
  setErrorMsg: (msg: string | null) => void;
  setSuccessMsg: (msg: string | null) => void;
  documents: Document[];
}

interface UseEncodeFormReturn {
  data: { document_id: string; carriers: File[] };
  setData: (key: string, value: any) => void;
  post: (url: string, options?: any) => void;
  processing: boolean;
  reset: () => void;
  step: number;
  setStep: React.Dispatch<React.SetStateAction<1 | 2>>;
  selectedCarriers: CarrierInfo[];
  isSelectingCarriers: boolean;
  useSystemCarriers: boolean;
  setUseSystemCarriers: React.Dispatch<React.SetStateAction<boolean>>;
  handleSubmit: (e: React.FormEvent) => void;
  selectedDoc: Document | undefined;
  dataNeeded: number;
  capacityOk: boolean;
  canGoNext1: boolean;
  canSubmit: boolean;
}

export function useEncodeForm({
  carriers,
  setCarriers,
  autoSelectedCarriers,
  clearAutoSelection,
  clearPreflight,
  handlePreflightCheck,
  getPreflightParams,
  setErrorMsg,
  setSuccessMsg,
  documents,
}: UseEncodeFormParams): UseEncodeFormReturn {
  const {
    step,
    setStep,
    selectedCarriers,
    setSelectedCarriers,
    isSelectingCarriers,
    useSystemCarriers,
    setUseSystemCarriers,
    runCarrierSelection,
  } = useStegoEncode({ initialStep: 1 });

  const { data, setData, post, processing, reset } = useForm<{
    document_id: string;
    carriers: File[];
  }>({
    document_id: '',
    carriers: [],
  });

  // Computed values
  const selectedDoc = documents.find((d) => String(d.id) === data.document_id);
  const dataNeeded = selectedDoc ? dataNeededBytes(selectedDoc.size) : 0;
  const totalCapacity = carriers.reduce((sum, c) => sum + (c.capacity ?? 0), 0);
  const allLoaded = carriers.length > 0 && carriers.every((c) => !c.loading);
  const capacityOk = allLoaded && totalCapacity >= dataNeeded;

  const canGoNext1 = !!data.document_id;
  const canSubmit = canGoNext1 && (carriers.length > 0 || useSystemCarriers || autoSelectedCarriers.length > 0) && capacityOk;

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg(null);
    setSuccessMsg(null);
    
    // Run preflight verification
    const verification = await handlePreflightCheck(getPreflightParams());
    if (!verification.passed) {
      return;
    }
    
    // Reuse shared orchestration hook for carrier selection behavior.
    runCarrierSelection(dataNeeded, carriers as any);
    
    post(route('stego.encode'), {
      forceFormData: true,
      preserveState: true,
      onSuccess: () => {
        const totalCarriers = carriers.length + autoSelectedCarriers.length;
        setSuccessMsg(`✅ Document encoded and hidden in ${totalCarriers} carrier(s) successfully!`);
        setCarriers([]);
        clearAutoSelection();
        reset();
        setStep(1);
        clearPreflight();
        setSelectedCarriers([]);
      },
      onError: (errs) => {
        setStep(2);
        // Collect the first error message to show in the banner
        const firstErr = Object.values(errs)[0];
        if (firstErr) {
          setErrorMsg(String(firstErr));
        }
      },
    });
  }, [
    carriers,
    setCarriers,
    autoSelectedCarriers,
    clearAutoSelection,
    clearPreflight,
    handlePreflightCheck,
    getPreflightParams,
    setErrorMsg,
    setSuccessMsg,
    post,
    reset,
    useSystemCarriers,
    runCarrierSelection,
    dataNeeded,
  ]);

  return {
    data,
    setData,
    post,
    processing,
    reset,
    step,
    setStep: setStep as React.Dispatch<React.SetStateAction<1 | 2>>,
    selectedCarriers,
    isSelectingCarriers,
    useSystemCarriers,
    setUseSystemCarriers,
    handleSubmit,
    selectedDoc,
    dataNeeded,
    capacityOk,
    canGoNext1,
    canSubmit,
  };
}

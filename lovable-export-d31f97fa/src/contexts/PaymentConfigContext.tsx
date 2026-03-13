import { createContext, useContext, useState, useEffect, type ReactNode } from "react";
import { clientApi } from "@/lib/api/dashboard";

export interface PaymentGatewayState {
  stripe: boolean;
  crypto: boolean;
  cryptomus: boolean;
  stripe_vat: number;
  cryptomus_vat: number;
  nowpayments_vat: number;
  manual_vat: number;
  nowpayments: boolean;
  coinbase: boolean;
  coinbase_vat: number;
  binance_pay_id?: string | null;
  binance_pay_instructions?: string | null;
}

interface PaymentConfigContextValue {
  gateways: PaymentGatewayState;
  settings: PaymentGatewayState; // Add settings alias for compatibility with ManualCryptoDialog
  toggleGateway: (id: keyof PaymentGatewayState) => void;
  autoTopUpEnabled: boolean;
  setAutoTopUpEnabled: (v: boolean) => void;
  loading: boolean;
}

const PaymentConfigContext = createContext<PaymentConfigContextValue | null>(null);

export function PaymentConfigProvider({ children }: { children: ReactNode }) {
  const [gateways, setGateways] = useState<PaymentGatewayState>({
    stripe: false,
    crypto: false,
    cryptomus: false,
    nowpayments: false,
    stripe_vat: 22,
    cryptomus_vat: 0,
    nowpayments_vat: 0,
    coinbase: false,
    coinbase_vat: 0,
    manual_vat: 0,
  });
  const [autoTopUpEnabled, setAutoTopUpEnabled] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadConfig() {
      try {
        const config = await clientApi.getGateways();
        setGateways({
          stripe: config.stripe,
          crypto: config.crypto,
          cryptomus: config.cryptomus,
          nowpayments: (config as any).nowpayments || false,
          stripe_vat: config.stripe_vat ?? 22,
          cryptomus_vat: config.cryptomus_vat ?? 0,
          nowpayments_vat: (config as any).nowpayments_vat ?? 0,
          coinbase: (config as any).coinbase || false,
          coinbase_vat: (config as any).coinbase_vat ?? 0,
          manual_vat: config.manual_vat ?? 0,
          binance_pay_id: config.binance_pay_id,
          binance_pay_instructions: config.binance_pay_instructions,
        });
      } catch (err) {
        console.error("Failed to load payment config", err);
      } finally {
        setLoading(false);
      }
    }
    loadConfig();
  }, []);

  const toggleGateway = (id: keyof PaymentGatewayState) => {
    setGateways((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  return (
    <PaymentConfigContext.Provider value={{ gateways, settings: gateways, toggleGateway, autoTopUpEnabled, setAutoTopUpEnabled, loading }}>
      {children}
    </PaymentConfigContext.Provider>
  );
}

export function usePaymentConfig() {
  const ctx = useContext(PaymentConfigContext);
  if (!ctx) throw new Error("usePaymentConfig must be used within PaymentConfigProvider");
  return ctx;
}

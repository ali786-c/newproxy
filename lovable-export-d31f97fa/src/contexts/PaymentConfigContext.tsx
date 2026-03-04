import { createContext, useContext, useState, useEffect, type ReactNode } from "react";
import { clientApi } from "@/lib/api/dashboard";

export interface PaymentGatewayState {
  stripe: boolean;
  crypto: boolean;
  cryptomus: boolean;
}

interface PaymentConfigContextValue {
  gateways: PaymentGatewayState;
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
          cryptomus: config.cryptomus
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
    <PaymentConfigContext.Provider value={{ gateways, toggleGateway, autoTopUpEnabled, setAutoTopUpEnabled, loading }}>
      {children}
    </PaymentConfigContext.Provider>
  );
}

export function usePaymentConfig() {
  const ctx = useContext(PaymentConfigContext);
  if (!ctx) throw new Error("usePaymentConfig must be used within PaymentConfigProvider");
  return ctx;
}

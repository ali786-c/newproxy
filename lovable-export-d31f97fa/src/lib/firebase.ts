import { initializeApp, getApps } from "firebase/app";
import { getAuth } from "firebase/auth";

// Firebase project: upgraderproxy
const firebaseConfig = {
  apiKey: "AIzaSyBp9RfA0IoikthkFxxr99sdQG-DLZG81Kw",
  authDomain: "upgraderproxy.firebaseapp.com",
  projectId: "upgraderproxy",
  storageBucket: "upgraderproxy.firebasestorage.app",
  messagingSenderId: "757181549583",
  appId: "1:757181549583:web:abaa74020658072e66e77a",
  measurementId: "G-YLDCRVDDDQ",
};

// Prevent re-initializing if already done (React HMR safe)
const app = getApps().length === 0 ? initializeApp(firebaseConfig) : getApps()[0];

export const firebaseAuth = getAuth(app);

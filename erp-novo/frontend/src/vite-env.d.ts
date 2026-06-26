/// <reference types="vite/client" />

// Google Maps JS SDK é carregado dinamicamente (lib/googleMaps.ts) só na tela de
// cercas; não usamos @types/google.maps. Declaração mínima para o TS aceitar.
declare const google: any

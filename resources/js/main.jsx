// import "./Pages/bootstrap"
import React from 'react';
import { createRoot } from 'react-dom/client';


const container = document.getElementById('root');
if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
        </React.StrictMode>
    );
}
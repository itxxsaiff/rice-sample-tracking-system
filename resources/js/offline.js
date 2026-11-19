import * as bootstrap from 'bootstrap';
import { initUi } from './modules/ui';
import { bootOfflineApp } from './offline/app/main';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    initUi();
    bootOfflineApp();
});

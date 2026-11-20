import * as bootstrap from 'bootstrap';
import { initUi } from './modules/ui';
import { initImageInputs } from './modules/images';
import { initPickers } from './modules/picker';
import { initChoiceLists } from './modules/choices';
import { initQuickAdd } from './modules/quick-add';
import { initSampleRows } from './modules/sample-rows';
import { initResponseForm } from './modules/response';
import { initOfflineBridge } from './offline/bridge';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    initUi();
    initImageInputs();
    initPickers();
    initChoiceLists();
    initQuickAdd();
    initSampleRows();
    initResponseForm();
    initOfflineBridge();
});

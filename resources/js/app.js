import Alpine from 'alpinejs';
import {aiSettings,questionEditor} from './editors';
window.Alpine = Alpine;
Alpine.data('aiSettings',aiSettings);
Alpine.data('questionEditor',questionEditor);
Alpine.start();

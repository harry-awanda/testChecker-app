import Alpine from 'alpinejs';
import {workspace} from './workspace';
import {aiSettings,questionEditor} from './editors';
window.Alpine = Alpine;
Alpine.data('aiSettings',aiSettings);
Alpine.data('questionEditor',questionEditor);
Alpine.data('workspace',workspace);
Alpine.start();

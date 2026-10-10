import axios from 'axios';
import Alpine from 'alpinejs';
import UIkit from 'uikit';
import Icons from 'uikit/dist/js/uikit-icons';
import { confirmedForm } from './admin/confirmed-form';
import { entityPicker } from './admin/entity-picker';
import { sortableRows } from './admin/sortable-rows';
import { explorer } from './explorer/explorer';

UIkit.use(Icons);

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios = axios;
window.UIkit = UIkit;
window.Alpine = Alpine;

Alpine.data('explorer', explorer);
Alpine.data('entityPicker', entityPicker);
Alpine.data('confirmedForm', confirmedForm);
Alpine.data('sortableRows', sortableRows);

Alpine.start();

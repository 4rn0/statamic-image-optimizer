import '../css/addon.css';
import Fieldtype from './components/ImageOptimizerFieldtype.vue';
import Executable from './components/ImageOptimizerExecutable.vue';
import Utility from './components/ImageOptimizerUtility.vue';
import Settings from './components/ImageOptimizerSettings.vue';
import UtilityPage from './pages/Utility.vue';

Statamic.booting(() => {

    Statamic.$components.register('image_optimizer-fieldtype', Fieldtype);
    Statamic.$components.register('image_optimizer_executable-fieldtype', Executable);
    Statamic.$components.register('image_optimizer-utility', Utility);
    Statamic.$components.register('image_optimizer-settings', Settings);
    Statamic.$inertia.register('imageoptimizer::Utility', UtilityPage);

});

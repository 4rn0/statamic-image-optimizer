import Fieldtype from './components/ImageOptimizerFieldtype.vue';
import Utility from './components/ImageOptimizerUtility.vue';
import UtilityPage from './pages/Utility.vue';

Statamic.booting(() => {

    Statamic.$components.register('image_optimizer-fieldtype', Fieldtype);
    Statamic.$components.register('image_optimizer-utility', Utility);
    Statamic.$inertia.register('imageoptimizer::Utility', UtilityPage);

});

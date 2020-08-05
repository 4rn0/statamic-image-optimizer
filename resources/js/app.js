import Fieldtype from './components/ImageOptimizerFieldtype';
import Utility from './components/ImageOptimizerUtility';

Statamic.booting(() => {

    Statamic.$components.register('image_optimizer-fieldtype', Fieldtype);
    Statamic.$components.register('image_optimizer-utility', Utility);

});

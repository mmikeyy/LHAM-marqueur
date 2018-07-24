import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
import { decorateModuleRef } from '../environment';

import { AppModule } from './app.module';

platformBrowserDynamic().bootstrapModule(AppModule).then(decorateModuleRef).catch((err) => console.error(err));

import { MyTranslationLoader } from './myTranslationLoader';
import {
  FakeMissingTranslationHandler, TranslateDefaultParser, TranslateFakeCompiler, TranslateLoader
} from '@ngx-translate/core';
import { TranslateService } from '@ngx-translate/core';
import { TranslateStore } from '@ngx-translate/core/src/translate.store';
import { Observable } from 'rxjs/Observable';

export let translationStores: {[context: string]: TranslateStore} = {};

  export let TranslationProviders: {[context: string]: TranslateService} = {};

let providers = TranslationProviders;

export function contextualizedTranslationServiceFactory(context) {
  return () => {
    if (providers[context]) {
      return providers[context];
    }
    let loader = new MyTranslationLoader();
    if (!translationStores[context]) {
      translationStores[context] = new TranslateStore();
    }
    let serv = new TranslateService(translationStores[context], loader, new TranslateFakeCompiler(), new TranslateDefaultParser(), new FakeMissingTranslationHandler(), false);
    loader.setup(serv, context);
    providers[context] = serv;
    return serv;
  };
}

export function TrProviderFactory() {
  return contextualizedTranslationServiceFactory;
}
// export class ContextCourriels {
//   public context = 'courriels';
// }
//
// let fnCourriel = contextualizedTranslationServiceFactory('courriels');
// export let provider_courriel = fnCourriel();
//
// console.log('................................... prov courr', provider_courriel);
// export let providerCourriels = {
//   provide: TranslateService,
//   useFactory: () => providers['courriels']
// };
//
// let a = TITLE;

// export class CourTr extends TranslateService {
//   constructor() {
//     super(stores['courriels'], loaderCourriels, new TranslateDefaultParser(), new FakeMissingTranslationHandler(), false);
//   }
// }
export let  trProviders: {[tag: string]: TranslateService | any} = {};
export let createdProviders: {[key: string]: TranslateService} = {};

let reqContext = require.context('../assets/i18n/fr/', true, /.*\.json$/);
reqContext.keys().forEach(key => {
  let tag = key.replace(/(^.\/|\.json$)/g, '');
  // if (tag === 'default') {
  //   return;
  // }
  console.log('key', key, '->', tag);
  trProviders[tag] =  class extends  TranslateService {
    constructor() {
      let loader = new MyTranslationLoader();
      translationStores[tag] = new TranslateStore();
      super(translationStores[tag], loader, new TranslateFakeCompiler(), new TranslateDefaultParser(), new FakeMissingTranslationHandler(), false);
      loader.setup(this, tag);
      // console.log('cccccccccconstructed trservice', tag);
    }
  };
  createdProviders[tag] = new trProviders[tag];
});

let translationFiles = require.context('../assets/i18n/', true, /.*\.json$/);
export class ContextTranslationLoader implements TranslateLoader {
  constructor(private context: string) {

  }

  getTranslation(lang: string = 'fr') {
    return Observable.of(translationFiles(`./${lang}/${this.context}.json`));
  }
}

export function getProvider(context: string) {
  return createdProviders[context];
}

export class TrProvider {
  static useFactory(tag) {
    return {
      provide: TranslateService,
      useFactory: function() {
        return trProviders[tag];
      }
    };
  }
  static useClass(tag) {
    return {
      provide: TranslateService,
      useClass: <TranslateService> trProviders[tag]
    };
  }
}

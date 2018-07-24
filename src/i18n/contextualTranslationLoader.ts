import { Inject, Injectable } from '@angular/core';

// import {TranslateLoader} from 'ng2-translate/ng2-translate';

// import {TranslateService} from '@ngx-translate/core';
import { LanguageService } from './language.service';

import { Observable } from 'rxjs/Rx';
import { Http } from '@angular/http';
import { TranslateLoader } from '@ngx-translate/core';
import { CONTEXT, TranslationContext } from './translation.context';

export let createContextualLoader = (context) => {
  return new ContextualTranslationLoader(context);
};

@Injectable()
export class ContextualTranslationLoader implements TranslateLoader {

  static observables = {};
  static translations = {};

  static getLang() {
    return LanguageService.currentLang;
  }

  public error = false;
  public translations;
  public context: string;

  private langObserver;
  private reqContext;

  constructor(@Inject(CONTEXT) public translationContext: TranslationContext) {

    this.context = this.translationContext.context;
    /// console.log('CCCCCCCCreating contextualtranslationloader', this.context);
    this.reqContext = require.context('../assets/i18n/', true, /.*\.json$/);

    LanguageService.emitChange();
  }

  translationFileName() {
    let name = `./${ContextualTranslationLoader.getLang()}/${this.context || 'default'}.json`;
    return name;
  }

  isTranslatable(str: string): boolean {
    if (this.context === 'default') {
      return this.isTranslatableContext(str);
    }
    return this.isTranslatableContext(str, this.context) || this.isTranslatableContext(str);
  }

  isTranslatableContext(str: string, context: string = 'default'): boolean {
    let def = ContextualTranslationLoader.translations[context];
    return !!(def && def[str]);
  }

  getTranslation(lang: string): Observable<any> {

    // let context = this.reqContext(tag);
    // console.log('....................................... context = ', context);
    if (!this.translations) {

      let tag = this.translationFileName();
      this.translations = this.reqContext(tag);
      // console.log('GGGGGGGGGGGGetting translations for ', this.context, this.translations);
    }
    return Observable.of(this.translations);

  }

  log(a: any, b?: any, c?: any) {

    if (LanguageService.debug) {
      console.log.apply(console, arguments);
      console.trace();
    }
  }

}

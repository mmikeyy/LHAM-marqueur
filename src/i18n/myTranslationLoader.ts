import { Injectable } from '@angular/core';

import { LanguageService } from './language.service';

import { Observable } from 'rxjs/Rx';
import { TranslateLoader } from '@ngx-translate/core';
import { TranslateService } from '@ngx-translate/core';

// @Injectable()
export class MyTranslationLoader implements TranslateLoader {
  static observables = {};
  static translations = {};
  static listeServices = [];

  static getLang() {
    return LanguageService.currentLang;
  }

  static indexServ(serv) {
    return MyTranslationLoader.listeServices.indexOf(serv);
  }

  static registerServ(serv) {
    if (MyTranslationLoader.indexServ(serv) === -1) {
      MyTranslationLoader.listeServices.push(serv);
    }
  }

  public error = false;
  public translations;
  private langObserver;
  private reqContext;

  constructor(private trans?: TranslateService, public context?: string) {
    this.setup();
  }

  setup(trans?, context?) {
    LanguageService.init();
    this.trans = trans || this.trans || null;
    if (context) {
      this.context = context;
    }
    if (this.trans && this.context) {
      MyTranslationLoader.registerServ(this.trans);
      // console.log('CCCCCCCCCCCCCCCCreate loader', this.context, 'no ' + MyTranslationLoader.indexServ(this.trans));
      this.reqContext = require.context('../assets/i18n/', true, /.*\.json$/);
      // let req = this.reqContext.keys();

      this.trans.currentLoader = this;
      this.langObserver = LanguageService.changeEmitter.subscribe((lang) => {
        this.trans.use(lang);
      });

      LanguageService.emitChange();

    }

  }

  translationFileName() {
    return `./${MyTranslationLoader.getLang()}/${this.context || 'default'}.json`;

  }

  isTranslatable(str: string): boolean {

    if (this.context === 'default') {
      return this.isTranslatableContext(str);
    }
    return this.isTranslatableContext(str, this.context) || this.isTranslatableContext(str);
  }

  isTranslatableContext(str: string, context: string = 'default'): boolean {
    let def = MyTranslationLoader.translations[context];
    return !!(def && def[str]);
  }

  getTranslation(lang: string): Observable<any> {

    // let context = this.reqContext(tag);
    if (!this.translations) {
      let tag = this.translationFileName();
      this.translations = this.reqContext(tag);
    }
    // console.log('.get translation.......................... context = ', this.context);
    // console.log('Translations for ' + this.context, this.translations);
    return Observable.of(this.translations);

  }

  log(a: any, b?: any, c?: any) {

    if (LanguageService.debug) {
      console.log.apply(console, arguments);
      console.trace();
    }
  }

}

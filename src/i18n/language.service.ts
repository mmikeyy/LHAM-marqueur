/**
 * Created by micra_000 on 2016-04-15.
 */
/////<reference path="../../typings/rx.all.d.ts"/>

import { EventEmitter, Directive } from '@angular/core';

import {

  TranslateService

} from '@ngx-translate/core';
import { Http } from '@angular/http';
import { MyTranslationLoader } from './myTranslationLoader';

// @Directive({})
export class LanguageService {
  static langs = ['fr', 'en'];
  static defaultLang = 'fr';
  static currentLang = 'fr';
  static changeEmitter: EventEmitter<string>;
  static nb = 0;
  static asyncEventEmitter = false; // set false for testing
  static debug = true;
  static http;

  static init() {
    if (LanguageService.changeEmitter === undefined) {
      LanguageService.changeEmitter = new EventEmitter<string>(LanguageService.asyncEventEmitter);
    }
  }

  static setLang(lang: string) {

    LanguageService.init();

    let currentLang = LanguageService.currentLang;
    LanguageService.currentLang = LanguageService.langs.indexOf(lang) > -1 ? lang : LanguageService.defaultLang;
    if (LanguageService.currentLang !== currentLang) {
      LanguageService.emitChange();
    }
    return LanguageService.currentLang;
  }

  static changeLang() {

    let nouvIndex = LanguageService.langs.indexOf(LanguageService.currentLang) + 1;

    nouvIndex = nouvIndex >= LanguageService.langs.length ? 0 : nouvIndex;
    LanguageService.setLang(LanguageService.langs[nouvIndex]);
  }

  static setupService(context: string,
                      // loader: MyTranslationLoader,
                      translateService: TranslateService,
                      newService?: boolean) {
    let serv;
    if (newService) {
      serv = new TranslateService(
        translateService.store,
        translateService.currentLoader,
        translateService.compiler,
        translateService.parser,
        translateService.missingTranslationHandler
      ); // translateService.currentLoader, null
    } else {
      // console.log('service existe ' + context);
      serv = translateService;
    }
    LanguageService.init();
    LanguageService.injectLoader(context, serv);
    serv.setDefaultLang('fr');
    serv.use(LanguageService.currentLang);
    // console.log('......... using lang ' + LanguageService.currentLang);

    return serv;
  }

  static injectLoader(context: string, translateService: TranslateService): void {
    new MyTranslationLoader(translateService, context);
  }

  static emitChange() {
    LanguageService.changeEmitter.emit(LanguageService.currentLang);
  }

  constructor(http: Http) {
    console.log('Construction service');
    LanguageService.init();
    LanguageService.http = http;
  }

  // static setupService(
  //     context: string,
  //     // loader: MyTranslationLoader,
  //     translateService: TranslateService
  // ) {
  //     LanguageService.init();
  //     LanguageService.injectLoader(context, translateService);
  //     translateService.setDefaultLang('fr');
  //     translateService.use(LanguageService.currentLang);
  // }

}

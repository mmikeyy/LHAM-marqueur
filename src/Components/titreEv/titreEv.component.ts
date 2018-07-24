import { Component, EventEmitter, OnInit, OnDestroy, Input, Output, ViewChild } from '@angular/core';
import { AlertController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../services/fmService';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: 'fm-titre-ev',
    templateUrl: './titreEv.component.html',
    styles: [
      '>>> .fm-ev-header .green-background{background-color: green!important}',
      '>>> .fm-ev-header .red-background{background-color: red!important}',
      '>>> .fm-ev-header span.button-inner{color: white!important}'
    ]
  }
)
export class TitreEvComponent implements OnInit, OnDestroy {

  @Input() ev;
  @Input() editable;
  @Output() clickMenu = new EventEmitter();

  public titres = {
    but: 'But',
    fin_periode: 'Fin de période',
    punition: 'Punition',
    changement_gardien: 'Changement de gardien',
    fusillade: 'Fusillade'
  };

  public subs: Subscription[] = [];

  @ViewChild('slidingMenu') public slidingMenu;

  constructor(
    public fmServ: FmService,
    public alertCtrl: AlertController
  ) {

  }

  ngOnInit() {
    this.subs.push(
      this.fmServ.closeSlidingEvMenu.subscribe(() =>
      this.slidingMenu.close()
      )
    );
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  titre() {
    return R.propOr('Type inconnu', this.ev.get('type_enreg'), this.titres);
    // switch(this.ev.get('type_enreg')) {
    //   case 'but':
    //     return 'But';
    //   case 'fin_periode':
    //     return 'Fin de période';
    //   case 'punition':
    //     return 'Punition';
    //   default:
    //     return 'Type inconnu'
    // }
  }

  confirmerEffacement() {
    this.fmServ.closeSlidingEvMenu.emit();
    let alert = this.alertCtrl.create({
      title: 'Effacement',
      message: 'Vous voulez bien effacer cet événement?',
      buttons: [
        {
          text: 'Annuler',
          cssClass: 'red-background'
        },
        {
          text: 'Oui',
          cssClass: 'green-background',
          handler: () => {
            this.clickMenu.emit('delete')
          }
        }
      ]
    });
    alert.present();
  }
}

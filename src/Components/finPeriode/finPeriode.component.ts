import { Component, EventEmitter, OnInit, OnDestroy, Input } from '@angular/core';
import { AlertController, NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import { NgRedux, select } from '@angular-redux/store';
import { EditeFinPeriodeComponent } from '../../pages/feuilleMatch/editeFinPeriode/editeFinPeriode.component';
import { FmService } from '../../services/fmService';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: 'fm-fin-periode',
    templateUrl: './finPeriode.component.html'
  }
)
export class FinPeriodeComponent implements OnInit, OnDestroy {

  @Input() ev;
  @Input() editable;
  public subs: Subscription[] = [];

  @select(['pratiques', 'ressources']) $ressources;

  constructor(
    public store: NgRedux<IAppState>,
    public navCtrl: NavController,
    public fmServ: FmService,
    public alertCtrl: AlertController
  ) {

  }

  ngOnInit() {
    this.subs.push();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  async click(op) {
    this.fmServ.closeSlidingEvMenu.emit();
    console.log('click fin période');
    switch (op) {
      case 'edit':
        this.navCtrl.push(EditeFinPeriodeComponent, {ev: this.ev, nouv: null});
        return;
      case 'delete': {
        let nbEv = this.store.getState().mesMatchs
          .get('feuilleMatch')
          .filter(ev => ev.get('periode') === this.ev.get('periode') && ev.get('type_enreg') !== 'fin_periode')
          .size;
        if (nbEv) {
          let alert = this.alertCtrl.create({
            title: 'Effacement',
            message: 'Retirez d\'abord tous les événements précédant la fin de la période',
            buttons: [
              {
                text: 'OK'
              }
            ]
          });
          alert.present();
        } else {
          let succes = await this.fmServ.effacerEv(this.ev);
          if (succes) {
            this.navCtrl.pop();
          }
        }
        return;
      }
      default:
    }

  }
}

import { Component, EventEmitter, OnInit, OnDestroy, Input } from '@angular/core';
import { AlertController, NavController, NavParams } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../../services/fmService';
import { MesMatchsActions } from '../../../store/mesMatchs/mesMatchs.actions';
import { FeuilleMatch, Fusillade } from '../../../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';
import { EditeFusilladeComponent } from '../editeFusillade/editeFusillade.component';

@Component(
  {
    selector: 'fm-fusillade',
    templateUrl: './fusillade.component.html'
  }
)
export class FusilladeComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public R = R;
  public ev: FeuilleMatch;
  public idFMFusilladeChargee: string;
  public liste: Fusillade[];
  public rondes: number[] = [];
  public prochaineRonde: number = 1;
  public editable = false;


  @select(['mesMatchs', 'idFMFusilladeChargee'])  $idFMFusilladeChargee;
  @select(['mesMatchs', 'fusillade'])  $fusillade;
  @select(['mesMatchs', 'feuilleMatch']) $feuilleMatch;

  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    public params: NavParams,
    public mesMatchsAct: MesMatchsActions,
    public navCtrl: NavController,
    public alertCtrl: AlertController
  ) {
    this.ev = this.params.get('ev');
    this.editable = this.params.get('editable');
  }

  ngOnInit() {
    this.subs.push(
      this.$idFMFusilladeChargee.subscribe(id => {
        this.idFMFusilladeChargee = id;
        this.loadFusillade();
      }),
      this.$fusillade.subscribe(liste => {
        this.liste = liste.toJS();
        this.rondes = R.pipe(
          R.pluck('ronde'),
          R.uniq,
          R.sortBy(R.identity)
        )(this.liste) as number[];
        this.prochaineRonde = (R.last(this.rondes) || 0) + 1;
      }),
      this.$feuilleMatch.subscribe(liste => {
        let updatedEv = liste.find(ev => ev.get('id') === this.ev.id);
        if (updatedEv) {
          this.ev = updatedEv.toJS();
        }
      })
    );
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }
  loadFusillade(force = false) {
    if (force || this.ev.id !== this.store.getState().mesMatchs.get('idFMFusilladeChargee')) {
      this.mesMatchsAct.getFM(this.fmServ.idMatch, this.ev.id);
    }
  }

  click(op) {

  }

  ronde(noRonde) {
    return R.filter(R.propEq('ronde', noRonde), this.liste);
  }

  rondeEq(ronde, eq) {
    return item => item.ronde === ronde
  }

  editeFusillade() {
    this.navCtrl.push(EditeFusilladeComponent, {ev: this.ev})
  }

  effacerFusillade() {
    let alert = this.alertCtrl.create({
      message: 'Effacer la fusillade?',
      buttons: [
        {
          text: 'Non'
        },
        {
          text: 'Oui',
          handler: () => {
            this.navCtrl.pop();
            this.fmServ.effacerEv(this.ev);
          }
        }
      ]
    });
    alert.present();
  }
}

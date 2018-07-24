import { Component, OnInit, OnDestroy } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../services/fmService';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: '',
    templateUrl: './collecterStats.component.html',
    styles: [
      `th, td{padding: 1rem}`,
      `th:not(:first-child), td:not(:first-child){text-align: center}`,
      `div.title{
            text-align: center;
            background-color: #666;
            color: white;
            margin-bottom: 1em;
            box-shadow: 4px 4px 4px #666;
            border-radius: 4px;
        }`,
      `div.content{
            text-align: left;
            margin: auto;
            display: inline-block;
            width: auto;
        }`
    ]
  }
)
export class CollecterStatsComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public idMatch;
  public mode = 'info';
  public erreur;
  public dataVerif;
  public R = R;


  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.idMatch = this.store.getState().mesMatchs.get('idMatch');
    this.subs.push();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  async collecterStats(sauvegarder = false) {
    this.erreur = null;
    let data = await this.fmServ.collecterStats(this.idMatch, sauvegarder);
    if (!data.result) {
      this.mode = 'erreur_verif';
      if (data.erreur) {
        this.erreur = data.erreur;
      } else {
        this.erreur = {
          type: 'generique',
          msg: R.propOr('Les statistiques ne peuvent être extraites de la feuille de match', 'msg', data)
        }
      }
    } else {
      if (data.stats_acceptees) {
        this.fmServ.toast('Statistiques enregistrées');
        this.navCtrl.pop();
        return;
      }
      this.mode = 'verif_ok';
      this.dataVerif = data;
      console.log(data);
    }
  }

  nomJoueur(id) {
    return R.pathOr('?', ['data_membres', id, 'nom'], this.dataVerif);
  }

  nomEquipe(id) {
    return R.pathOr('?', ['data_equipes', id, 'nom'], this.dataVerif);
  }

  floor(x){
    return Math.floor(x)
  }
}
